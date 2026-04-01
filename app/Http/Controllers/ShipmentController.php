<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;
use App\Models\Shipment;
use App\Models\Template;
use App\Models\Trailer;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        // Log incoming data for debugging
        \Log::info('Shipments index', [
            'method' => $request->method(),
            'payload' => $request->all(),
        ]);

        // Use $request->input() or $request->all() - NOT $request->query()
        $perPage = $request->input('per_page', 15);
        $search = trim($request->input('search') ?? '');

        $query = Shipment::query()->withCount('notes')
            ->with(['pickupLocation', 'dcLocation', 'carrier'])
            ->latest();

        if ($request->user()?->hasRole('carrier')) {
            $carrierId = $request->user()?->carrier_id;

            $query->where(function ($carrierQuery) use ($carrierId) {
                $carrierQuery->where('carrier_id', $carrierId)
                    ->orWhereHas('offeredCarriers', function ($offerQuery) use ($carrierId) {
                        $offerQuery->where('carriers.id', $carrierId);
                    });
            });
        }

        if ($request->boolean('only_unassigned')) {
            $query->whereNull('carrier_id');
        }

        // Apply filters from payload
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('bol', 'like', "%{$search}%")
                    ->orWhere('po_number', 'like', "%{$search}%")
                    ->orWhere('trailer', 'like', "%{$search}%");
            });
        }

        $excludedStatuses = $request->input('excluded_statuses', []);
        if ($excludedStatuses) {
            $query->whereNotIn('status', $excludedStatuses);
        }

        // Exclude shippers (pickup locations)
        if ($excludedShippers = $request->input('excluded_pickup_locations')) {
            $query->whereHas('pickupLocation', function ($q) use ($excludedShippers) {
                $q->whereNotIn('short_code', (array) $excludedShippers);
            });
        }

        // Exclude DCs
        if ($excludedDcs = $request->input('excluded_dc_locations')) {
            $query->whereHas('dcLocation', function ($q) use ($excludedDcs) {
                $q->whereNotIn('short_code', (array) $excludedDcs);
            });
        }

        // Exclude carriers
        $excludedCarriers = $request->input('excluded_carriers', []);

        // Special case: if user excluded ALL carriers → show only shipments with null carrier
        $allCarrierNames = Carrier::pluck('name')->unique()->toArray();
        if (is_array($excludedCarriers) && count(array_unique($excludedCarriers)) === count($allCarrierNames)) {
            $query->whereNull('carrier_id');
        }
        // Normal case: exclude the selected carriers
        elseif (! empty($excludedCarriers)) {
            $query->whereNotIn('carrier_id', function ($sub) use ($excludedCarriers) {
                $sub->select('id')
                    ->from('carriers')
                    ->whereIn('name', $excludedCarriers);
            });
        }

        // Drop Date filter
        $dropStart = $request->input('drop_start');
        $dropEnd = $request->input('drop_end');

        if ($dropStart && $dropEnd) {
            $query->whereDate('drop_date', '>=', $dropStart)
                ->whereDate('drop_date', '<=', $dropEnd);
        } elseif ($dropStart) {
            $query->whereDate('drop_date', '>=', $dropStart);
        } elseif ($dropEnd) {
            $query->whereDate('drop_date', '<=', $dropEnd);
        }

        $shipments = $query->paginate($perPage);

        $shipments->setCollection(
            $shipments->getCollection()
                ->groupBy(fn (Shipment $shipment): string => filled($shipment->consolidation_number)
                    ? 'consolidation:'.$shipment->consolidation_number
                    : 'shipment:'.$shipment->getKey())
                ->map(fn ($shipmentGroup) => filled($shipmentGroup->first()?->consolidation_number)
                    ? $shipmentGroup->sortBy('shipment_number', SORT_NATURAL)->values()
                    : $shipmentGroup->values())
                ->values()
                ->flatten(1)
                ->values()
        );

        // Transform to add has_notes if you use that indicator
        $shipments->getCollection()->transform(function (Shipment $shipment): array {
            return [
                'id' => $shipment->guid,
                'status' => $shipment->status,
                'bol' => $shipment->bol,
                'shipment_number' => $shipment->shipment_number,
                'consolidation_number' => $shipment->consolidation_number,
                'pickup_location' => $shipment->pickupLocation ? [
                    'id' => $shipment->pickupLocation->guid,
                    'short_code' => $shipment->pickupLocation->short_code,
                    'name' => $shipment->pickupLocation->name,
                ] : null,
                'dc_location' => $shipment->dcLocation ? [
                    'id' => $shipment->dcLocation->guid,
                    'short_code' => $shipment->dcLocation->short_code,
                    'name' => $shipment->dcLocation->name,
                ] : null,
                'drop_date' => $shipment->drop_date,
                'pickup_date' => $shipment->pickup_date,
                'delivery_date' => $shipment->delivery_date,
                'carrier' => $shipment->carrier ? [
                    'id' => $shipment->carrier->id,
                    'name' => $shipment->carrier->name,
                    'short_code' => $shipment->carrier->short_code,
                ] : null,
                'trailer' => $shipment->trailer,
                'notes_count' => $shipment->notes_count,
                'has_notes' => $shipment->notes()->exists(),
            ];
        });

        return Inertia::render('Admin/Shipments/Index', [
            'shipments' => $shipments,
            'statuses' => Shipment::distinct('status')->pluck('status')->sort()->values(),
            'all_shipper_codes' => Location::where('type', 'pickup')->pluck('short_code')->sort()->values(),
            'all_dc_codes' => Location::whereIn('type', ['distribution_center', 'pickup'])->pluck('short_code')->sort()->values(),
            'all_carrier_names' => Carrier::pluck('name')->sort()->values(),
            'googleSheetsUrl' => AppSetting::getValue(AppSetting::GOOGLE_SHEET_URL_KEY),
            // Pass current filters back for frontend state restoration
            'filters' => $request->only([
                'search',
                'only_unassigned',
                'excluded_statuses',
                'excluded_pickup_locations',
                'excluded_dc_locations',
                'excluded_carriers',
                'drop_start',
                'drop_end',
                'per_page',
            ]),
        ]);
    }

    public function create()
    {
        $pickupLocations = Location::where('type', 'pickup')
            ->orderBy('short_code')
            ->get(['id', 'guid', 'short_code', 'name'])
            ->map(fn (Location $location) => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'name' => $location->name,
            ])
            ->values();

        $dcLocations = Location::whereIn('type', ['distribution_center', 'pickup'])
            ->orderBy('short_code')
            ->get(['id', 'guid', 'short_code', 'name'])
            ->map(fn (Location $location) => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'name' => $location->name,
            ])
            ->values();

        $carriers = Carrier::select('id', 'name', 'short_code')
            ->where('is_active', true)
            ->get();

        $statuses = Shipment::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->pluck('status')
            ->map(fn (string $status): string => trim($status))
            ->filter(fn (string $status): bool => $status !== '')
            ->unique()
            ->sort()
            ->values();

        return Inertia::render('Admin/Shipments/Create', [
            'pickupLocations' => $pickupLocations,
            'dcLocations' => $dcLocations,
            'carriers' => $carriers,
            'statuses' => $statuses,
        ]);
    }

    public function store(Request $request)
    {
        $messages = [
            'pickup_date.after_or_equal' => 'The pickup date must be on or after the drop date.',
            'delivery_date.after_or_equal' => 'The delivery date must be on or after the pickup date.',
        ];

        $validated = $request->validate([
            'shipment_number' => ['required', 'unique:shipments'],
            'bol' => ['nullable', 'string', 'max:100'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string'],
            'pickup_location_id' => ['required', 'uuid', 'exists:locations,guid'],
            'dc_location_id' => ['nullable', 'uuid', 'exists:locations,guid'],
            'carrier_id' => ['nullable', 'uuid', 'exists:carriers,id'],
            'drop_date' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:drop_date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
            'rack_qty' => ['integer', 'min:0'],
            'load_bar_qty' => ['integer', 'min:0'],
            'strap_qty' => ['integer', 'min:0'],
            'trailer' => ['nullable', 'string', 'max:50'],
            'consolidation_number' => ['nullable', 'string', 'max:255'],
            'drayage' => ['nullable', 'string'],
            'on_site' => ['nullable', 'boolean'],
            'shipped' => ['nullable', 'boolean'],
            'recycling_sent' => ['nullable', 'boolean'],
            'paperwork_sent' => ['nullable', 'boolean'],
            'delivery_alert_sent' => ['nullable', 'boolean'],
        ], $messages);

        $validated['pickup_location_id'] = $this->resolveLocationIdByGuid($validated['pickup_location_id'] ?? null);
        $validated['dc_location_id'] = $this->resolveLocationIdByGuid($validated['dc_location_id'] ?? null);

        Shipment::create($validated);

        return redirect()->route('admin.shipments.index')
            ->with('success', 'Shipment created successfully.');
    }

    public function show(Request $request, Shipment $shipment)
    {
        $shipment->load([
            'pickupLocation:id,short_code,name,address,city,state,country,zip,latitude,longitude',
            'dcLocation:id,short_code,name,address,city,state,country,zip,latitude,longitude,recycling_location_id',
            'dcLocation.recyclingLocation:id,short_code,name,latitude,longitude',
            'carrier:id,name,short_code',
            'offeredCarriers:id,name,short_code',
            'notes.user',
        ]);

        $availableCarriers = $request->user()?->hasRole(['administrator', 'supervisor'])
            ? Carrier::query()
                ->select('id', 'name', 'short_code')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        // Build ordered waypoints: pickup → DC → (Recycling if exists)
        $waypoints = [];

        if ($shipment->pickupLocation && $shipment->pickupLocation->latitude && $shipment->pickupLocation->longitude) {
            $waypoints[] = [
                'id' => $shipment->pickupLocation->guid,
                'short_code' => $shipment->pickupLocation->short_code,
                'name' => $shipment->pickupLocation->name,
                'type' => 'pickup',
                'lng' => $shipment->pickupLocation->longitude,
                'lat' => $shipment->pickupLocation->latitude,
            ];
        }

        if ($shipment->dcLocation && $shipment->dcLocation->latitude && $shipment->dcLocation->longitude) {
            $waypoints[] = [
                'id' => $shipment->dcLocation->guid,
                'short_code' => $shipment->dcLocation->short_code,
                'name' => $shipment->dcLocation->name,
                'type' => 'dc',
                'lng' => $shipment->dcLocation->longitude,
                'lat' => $shipment->dcLocation->latitude,
            ];
        }

        // Add recycling if assigned to the DC
        if ($shipment->dcLocation?->recyclingLocation && $shipment->dcLocation->recyclingLocation->latitude && $shipment->dcLocation->recyclingLocation->longitude) {
            $waypoints[] = [
                'id' => $shipment->dcLocation->recyclingLocation->guid,
                'short_code' => $shipment->dcLocation->recyclingLocation->short_code,
                'name' => $shipment->dcLocation->recyclingLocation->name,
                'type' => 'recycling',
                'lng' => $shipment->dcLocation->recyclingLocation->longitude,
                'lat' => $shipment->dcLocation->recyclingLocation->latitude,
            ];
        }

        $routeData = null;

        if (count($waypoints) >= 2) {
            // Build Mapbox Directions coordinates string: lng1,lat1;lng2,lat2;...
            $coordString = implode(';', array_map(fn ($wp) => "{$wp['lng']},{$wp['lat']}", $waypoints));

            $token = config('services.mapbox.key');

            if ($token) {
                try {
                    $response = Http::get("https://api.mapbox.com/directions/v5/mapbox/driving/{$coordString}", [
                        'access_token' => $token,
                        'geometries' => 'geojson',
                        'overview' => 'full',
                    ]);

                    if (! empty($response['routes'])) {
                        $route = $response['routes'][0];

                        $totalMiles = round(($route['distance'] / 1000) * 0.621371, 1);

                        // Calculate segment distances
                        $pickupToDcMiles = $totalMiles;
                        $dcToRecyclingMiles = 0;

                        if (count($waypoints) === 3) {
                            // Approximate: pickup->DC is 2/3, DC->recycling is 1/3
                            $pickupToDcMiles = round($totalMiles * 0.67, 1);
                            $dcToRecyclingMiles = round($totalMiles * 0.33, 1);
                        }

                        $routeData = [
                            'route_coords' => $route['geometry']['coordinates'] ?? [],
                            'total_km' => round($route['distance'] / 1000, 1),
                            'total_miles' => $totalMiles,
                            'pickup_to_dc_miles' => $pickupToDcMiles,
                            'dc_to_recycling_miles' => $dcToRecyclingMiles,
                            'duration' => $this->secondsToHumanTime($route['duration']),
                            'waypoints' => $waypoints,
                        ];
                    } else {
                        throw new Exception;
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch route for shipment {$shipment->id}");
                }
            }
        }

        $viewerIsCarrier = (bool) $request->user()?->hasRole('carrier');
        $viewerCarrierId = $request->user()?->carrier_id;

        // Rates for the lane (pickup → destination)
        $dcLocation = $shipment->dcLocation;

        // Base query for regular rates
        $ratesQuery = Rate::query()
            ->where(function ($query) {
                $query->whereNull('name')
                    ->orWhere('name', '!=', 'Recycling');
            })
            ->with('carrier:id,name,short_code');

        if ($viewerIsCarrier && $viewerCarrierId) {
            $ratesQuery->where(function ($query) use ($viewerCarrierId) {
                $query->where('carrier_id', $viewerCarrierId)
                    ->orWhereNull('carrier_id');
            });
        } elseif ($viewerIsCarrier) {
            $ratesQuery->whereNull('carrier_id');
        }
        // If carrier is assigned, show rates for that carrier and shared rates with no carrier.
        elseif ($shipment->carrier_id) {
            $ratesQuery->where(function ($query) use ($shipment) {
                $query->where('carrier_id', $shipment->carrier_id)
                    ->orWhereNull('carrier_id');
            });
        }

        // Get regular rates and keep only rates whose destination is within 60 miles of the shipment DC.
        $regularRates = $ratesQuery
            ->orderBy('carrier_id')
            ->orderBy('rate')
            ->get()
            ->filter(fn (Rate $rate): bool => $this->shouldIncludeRegularRateForShipment($shipment, $dcLocation, $rate))
            ->values();

        // Get recycling rates (separate query)
        $recyclingRates = Rate::query()
            ->where('name', 'Recycling')
            ->where(function ($query) use ($shipment) {
                $query->where('pickup_location_id', $shipment->dc_location_id)
                    ->orWhereNull('pickup_location_id');
            })
            ->with('carrier:id,name,short_code')
            ->when($viewerIsCarrier && $viewerCarrierId, function ($query) use ($viewerCarrierId) {
                $query->where(function ($carrierQuery) use ($viewerCarrierId) {
                    $carrierQuery->where('carrier_id', $viewerCarrierId)
                        ->orWhereNull('carrier_id');
                });
            })
            ->when($viewerIsCarrier && ! $viewerCarrierId, function ($query) {
                $query->whereNull('carrier_id');
            })
            ->when(! $viewerIsCarrier && $shipment->carrier_id, function ($query) use ($shipment) {
                $query->where(function ($carrierQuery) use ($shipment) {
                    $carrierQuery->where('carrier_id', $shipment->carrier_id)
                        ->orWhereNull('carrier_id');
                });
            })
            ->orderBy('carrier_id')
            ->orderBy('rate')
            ->get();

        // Combine rates, with regular rates first, then recycling rates
        $rates = $regularRates->merge($recyclingRates);

        // Transform rates to match frontend expectations
        $transformedRates = $rates->map(function ($rate) {
            // Determine calculation type
            $calculationType = 'full_route'; // default

            if ($rate->name === 'Recycling') {
                $calculationType = 'dc_to_recycling';
            } elseif ($rate->destination_city === null && $rate->destination_state === null && $rate->destination_country === null) {
                $calculationType = 'pickup_to_dc';
            }

            return [
                'id' => $rate->id,
                'carrier' => $rate->carrier,
                'rate_per_mile' => $rate->rate,
                'effective_date' => $rate->effective_from?->format('Y-m-d'),
                'expires_at' => $rate->effective_to?->format('Y-m-d'),
                'notes' => $rate->notes,
                'type' => $rate->type,
                'name' => $rate->name,
                'calculation_type' => $calculationType,
            ];
        });

        $shipmentData = $shipment->toArray();
        $shipmentData['id'] = $shipment->guid;
        $shipmentData['notable_id'] = $shipment->getKey();

        $offerUserNames = User::query()
            ->whereIn('id', $shipment->offeredCarriers->pluck('pivot.offered_by_user_id')->filter()->unique())
            ->pluck('name', 'id');

        $offeredCarriers = $shipment->offeredCarriers->map(fn (Carrier $carrier) => [
            'id' => $carrier->id,
            'name' => $carrier->name,
            'short_code' => $carrier->short_code,
            'offered_by_user' => $carrier->pivot->offered_by_user_id
                ? [
                    'id' => $carrier->pivot->offered_by_user_id,
                    'name' => $offerUserNames->get($carrier->pivot->offered_by_user_id),
                ]
                : null,
        ])->values();

        $canManageConsolidation = (bool) $request->user()?->hasRole(['administrator', 'supervisor']);

        $consolidatedShipments = collect();
        if (filled($shipment->consolidation_number)) {
            $consolidatedShipments = Shipment::query()
                ->where('consolidation_number', $shipment->consolidation_number)
                ->orderBy('shipment_number')
                ->get();
        }

        if ($consolidatedShipments->isEmpty()) {
            $consolidatedShipments = collect([$shipment]);
        }

        $consolidationMembers = $consolidatedShipments->map(fn (Shipment $groupShipment): array => [
            'id' => $groupShipment->guid,
            'shipment_number' => $groupShipment->shipment_number,
            'bol' => $groupShipment->bol,
            'rack_qty' => (int) ($groupShipment->rack_qty ?? 0),
            'load_bar_qty' => (int) ($groupShipment->load_bar_qty ?? 0),
            'strap_qty' => (int) ($groupShipment->strap_qty ?? 0),
            'carrier_id' => $groupShipment->carrier_id,
            'trailer_id' => $groupShipment->trailer_id,
        ])->values();

        $consolidationTotals = [
            'rack_qty' => $consolidatedShipments->sum(fn (Shipment $groupShipment): int => (int) ($groupShipment->rack_qty ?? 0)),
            'load_bar_qty' => $consolidatedShipments->sum(fn (Shipment $groupShipment): int => (int) ($groupShipment->load_bar_qty ?? 0)),
            'strap_qty' => $consolidatedShipments->sum(fn (Shipment $groupShipment): int => (int) ($groupShipment->strap_qty ?? 0)),
        ];

        $eligibleConsolidationShipments = Shipment::query()
            ->where('pickup_location_id', $shipment->pickup_location_id)
            ->where('dc_location_id', $shipment->dc_location_id)
            ->whereKeyNot($shipment->getKey())
            ->orderBy('shipment_number')
            ->get(['id', 'guid', 'shipment_number', 'bol', 'carrier_id', 'trailer_id'])
            ->map(fn (Shipment $candidate): array => [
                'id' => $candidate->guid,
                'shipment_number' => $candidate->shipment_number,
                'bol' => $candidate->bol,
                'carrier_id' => $candidate->carrier_id,
                'trailer_id' => $candidate->trailer_id,
            ])
            ->values();

        $selectedConsolidationShipmentIds = $consolidationMembers
            ->pluck('id')
            ->filter(fn (string $shipmentGuid): bool => $shipmentGuid !== $shipment->guid)
            ->values();

        $shipmentData['pickup_location'] = $shipment->pickupLocation ? [
            'id' => $shipment->pickupLocation->guid,
            'short_code' => $shipment->pickupLocation->short_code,
            'name' => $shipment->pickupLocation->name,
            'address' => $shipment->pickupLocation->address,
            'city' => $shipment->pickupLocation->city,
            'state' => $shipment->pickupLocation->state,
            'zip' => $shipment->pickupLocation->zip,
            'country' => $shipment->pickupLocation->country,
            'latitude' => $shipment->pickupLocation->latitude,
            'longitude' => $shipment->pickupLocation->longitude,
        ] : null;
        $shipmentData['dc_location'] = $shipment->dcLocation ? [
            'id' => $shipment->dcLocation->guid,
            'short_code' => $shipment->dcLocation->short_code,
            'name' => $shipment->dcLocation->name,
            'address' => $shipment->dcLocation->address,
            'city' => $shipment->dcLocation->city,
            'state' => $shipment->dcLocation->state,
            'zip' => $shipment->dcLocation->zip,
            'country' => $shipment->dcLocation->country,
            'latitude' => $shipment->dcLocation->latitude,
            'longitude' => $shipment->dcLocation->longitude,
            'recycling_location_id' => $shipment->dcLocation->recyclingLocation?->guid,
        ] : null;

        return Inertia::render('Admin/Shipments/Show', [
            'shipment' => $shipmentData,
            'route_data' => $routeData,
            'mapbox_token' => config('services.mapbox.key'),
            'rates' => $transformedRates,
            'hasAssignedCarrier' => (bool) $shipment->carrier_id,
            'googleSheetsUrl' => AppSetting::getValue(AppSetting::GOOGLE_SHEET_URL_KEY),
            'availableCarriers' => $availableCarriers,
            'offeredCarriers' => $offeredCarriers,
            'canManageConsolidation' => $canManageConsolidation,
            'consolidationData' => [
                'number' => $shipment->consolidation_number,
                'members' => $consolidationMembers,
                'totals' => $consolidationTotals,
                'eligible_shipments' => $eligibleConsolidationShipments,
                'selected_shipment_ids' => $selectedConsolidationShipmentIds,
            ],
        ]);
    }

    // Optional helper (add to controller if not already present)
    private function secondsToHumanTime($seconds)
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);

        return $h > 0 ? "{$h} hr {$m} min" : "{$m} min";
    }

    private function buildOfferSyncPayload(Shipment $shipment, array $offeredCarrierIds, ?int $offeredByUserId): array
    {
        $existingOfferUserIds = $shipment->offeredCarriers()
            ->get()
            ->mapWithKeys(fn (Carrier $carrier) => [
                $carrier->id => $carrier->pivot->offered_by_user_id,
            ]);

        return collect($offeredCarrierIds)
            ->mapWithKeys(fn (int $carrierId) => [
                $carrierId => [
                    'offered_by_user_id' => $existingOfferUserIds->get($carrierId, $offeredByUserId),
                ],
            ])
            ->all();
    }

    private function shouldIncludeRegularRateForShipment(Shipment $shipment, ?Location $dcLocation, Rate $rate): bool
    {
        // Global/fallback rates (no start or no end lane fields) should be visible on every shipment.
        if (blank($rate->pickup_location_id)
            || blank($rate->destination_city)
            || blank($rate->destination_state)
            || blank($rate->destination_country)) {
            return true;
        }

        if ((string) $rate->pickup_location_id !== (string) $shipment->pickup_location_id) {
            return false;
        }

        return $this->isRateDestinationWithinMilesOfDc($dcLocation, $rate, 60.0);
    }

    private function isRateDestinationWithinMilesOfDc(?Location $dcLocation, Rate $rate, float $maxMiles): bool
    {
        if (! $dcLocation || ! $dcLocation->latitude || ! $dcLocation->longitude) {
            return false;
        }

        $destinationLocations = Location::query()
            ->whereRaw('LOWER(city) = ?', [Str::lower((string) $rate->destination_city)])
            ->whereRaw('LOWER(state) = ?', [Str::lower((string) $rate->destination_state)])
            ->whereRaw('LOWER(country) = ?', [Str::lower((string) $rate->destination_country)])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        if ($destinationLocations->isEmpty()) {
            return false;
        }

        $closestMiles = $destinationLocations
            ->map(fn (Location $destinationLocation): float => $this->haversineDistanceMiles(
                (float) $dcLocation->latitude,
                (float) $dcLocation->longitude,
                (float) $destinationLocation->latitude,
                (float) $destinationLocation->longitude,
            ))
            ->min();

        return $closestMiles !== null && $closestMiles <= $maxMiles;
    }

    private function haversineDistanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3958.8;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMiles * $c;
    }

    private function syncTrailerAssignments(Shipment $shipment, array &$validated): void
    {
        $pickupLocationId = array_key_exists('pickup_location_id', $validated)
            ? $validated['pickup_location_id']
            : $shipment->pickup_location_id;

        $nextCarrierId = array_key_exists('carrier_id', $validated)
            ? $validated['carrier_id']
            : $shipment->carrier_id;

        if (blank($nextCarrierId)) {
            $validated['trailer_id'] = null;
            $validated['loaned_from_trailer_id'] = null;
        }

        $existingTrailerIds = collect([$shipment->trailer_id, $shipment->loaned_from_trailer_id])
            ->filter()
            ->unique();

        $nextTrailerIds = collect([
            array_key_exists('trailer_id', $validated) ? $validated['trailer_id'] : $shipment->trailer_id,
            array_key_exists('loaned_from_trailer_id', $validated) ? $validated['loaned_from_trailer_id'] : $shipment->loaned_from_trailer_id,
        ])->filter()->unique();

        $removedTrailerIds = $existingTrailerIds->diff($nextTrailerIds);

        if ($removedTrailerIds->isNotEmpty()) {
            Trailer::query()
                ->whereIn('id', $removedTrailerIds->all())
                ->update([
                    'current_location_id' => $pickupLocationId,
                    'status' => 'available',
                ]);
        }

        if ($nextTrailerIds->isNotEmpty()) {
            Trailer::query()
                ->whereIn('id', $nextTrailerIds->all())
                ->update([
                    'current_location_id' => $pickupLocationId,
                    'status' => 'in_use',
                ]);
        }
    }

    public function edit(Shipment $shipment)
    {
        $shipment->load(['pickupLocation', 'dcLocation', 'carrier', 'offeredCarriers:id']);

        $pickupLocations = Location::where('type', 'pickup')
            ->orderBy('name')
            ->get(['guid', 'short_code', 'name'])
            ->map(fn (Location $location): array => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'name' => $location->name,
            ])
            ->values();

        $dcLocations = Location::whereIn('type', ['distribution_center', 'pickup'])
            ->orderBy('name')
            ->get(['guid', 'short_code', 'name'])
            ->map(fn (Location $location): array => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'name' => $location->name,
            ])
            ->values();

        $carriers = Carrier::select('id', 'name', 'short_code')
            ->where('is_active', true)
            ->get();

        $statuses = Shipment::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->pluck('status')
            ->map(fn (string $status): string => trim($status))
            ->filter(fn (string $status): bool => $status !== '')
            ->unique()
            ->sort()
            ->values();

        // Convert to array and format dates for proper display in HTML date/datetime-local inputs
        $shipmentData = $shipment->toArray();
        $shipmentData['id'] = $shipment->guid;
        $shipmentData['drop_date'] = $this->formatDateValue($shipment->drop_date, 'Y-m-d');
        $shipmentData['pickup_date'] = $this->formatDateValue($shipment->pickup_date, 'Y-m-d\TH:i');
        $shipmentData['delivery_date'] = $this->formatDateValue($shipment->delivery_date, 'Y-m-d\TH:i');
        $shipmentData['on_site'] = $this->formatDateValue($shipment->on_site, 'Y-m-d\TH:i');
        $shipmentData['shipped'] = $this->formatDateValue($shipment->shipped, 'Y-m-d\TH:i');
        $shipmentData['crossed'] = $this->formatDateValue($shipment->crossed, 'Y-m-d\TH:i');
        $shipmentData['recycling_sent'] = $this->formatDateValue($shipment->recycling_sent, 'Y-m-d\TH:i');
        $shipmentData['paperwork_sent'] = $this->formatDateValue($shipment->paperwork_sent, 'Y-m-d\TH:i');
        $shipmentData['delivery_alert_sent'] = $this->formatDateValue($shipment->delivery_alert_sent, 'Y-m-d\TH:i');
        $shipmentData['offered_carrier_ids'] = $shipment->offeredCarriers->pluck('id')->all();
        $shipmentData['pickup_location_id'] = $shipment->pickupLocation?->guid;
        $shipmentData['dc_location_id'] = $shipment->dcLocation?->guid;

        return Inertia::render('Admin/Shipments/Edit', [
            'shipment' => $shipmentData,
            'pickupLocations' => $pickupLocations,
            'dcLocations' => $dcLocations,
            'carriers' => $carriers,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, Shipment $shipment)
    {
        $messages = [
            'pickup_date.after_or_equal' => 'The pickup date must be on or after the drop date.',
            'delivery_date.after_or_equal' => 'The delivery date must be on or after the pickup date.',
        ];

        $validated = $request->validate([
            'shipment_number' => 'unique:shipments,shipment_number,'.$shipment->id,
            'bol' => 'nullable|string|max:100',
            'po_number' => 'nullable|string|max:100',
            'status' => 'required|string',
            'pickup_location_id' => 'required|uuid|exists:locations,guid',
            'dc_location_id' => 'nullable|uuid|exists:locations,guid',
            'carrier_id' => 'nullable|uuid|exists:carriers,id',
            'offered_carrier_ids' => 'nullable|array',
            'offered_carrier_ids.*' => 'uuid|exists:carriers,id',
            'trailer_id' => 'nullable|exists:trailers,id',
            'loaned_from_trailer_id' => 'nullable|exists:trailers,id',
            'drop_date' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:drop_date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
            'rack_qty' => 'integer|min:0',
            'load_bar_qty' => 'integer|min:0',
            'strap_qty' => 'integer|min:0',
            'trailer' => 'nullable|string|max:50',
            'consolidation_number' => 'nullable|string|max:255',
            'drayage' => 'nullable|string',
            'on_site' => 'nullable|date',
            'shipped' => 'nullable|date',
            'recycling_sent' => 'nullable|date',
            'paperwork_sent' => 'nullable|date',
            'delivery_alert_sent' => 'nullable|date',
            'crossed' => 'nullable|date',
            'seal_number' => 'nullable|string|max:255',
            'drivers_id' => 'nullable|string|max:255',
        ], $messages);

        $validated['pickup_location_id'] = $this->resolveLocationIdByGuid($validated['pickup_location_id'] ?? null);
        $validated['dc_location_id'] = $this->resolveLocationIdByGuid($validated['dc_location_id'] ?? null);
        $validated['consolidation_number'] = isset($validated['consolidation_number'])
            ? trim((string) $validated['consolidation_number'])
            : null;
        $validated['consolidation_number'] = $validated['consolidation_number'] === ''
            ? null
            : $validated['consolidation_number'];

        $previousConsolidationNumber = $shipment->consolidation_number;

        $offeredCarrierIds = collect($validated['offered_carrier_ids'] ?? [])
            ->map(fn ($carrierId) => (string) $carrierId)
            ->unique()
            ->values();

        unset($validated['offered_carrier_ids']);

        $this->syncTrailerAssignments($shipment, $validated);

        $shipment->update($validated);

        if (array_key_exists('consolidation_number', $validated) && $previousConsolidationNumber !== $shipment->consolidation_number) {
            if (filled($previousConsolidationNumber)) {
                Shipment::query()
                    ->where('consolidation_number', $previousConsolidationNumber)
                    ->update(['consolidation_number' => $shipment->consolidation_number]);
            }
        }

        if ($shipment->carrier_id) {
            $shipment->offeredCarriers()->sync([]);
        } else {
            $shipment->offeredCarriers()->sync(
                $this->buildOfferSyncPayload($shipment, $offeredCarrierIds->all(), $request->user()?->id)
            );
        }

        return redirect()->route('admin.shipments.show', $shipment)
            ->with('success', 'Shipment updated successfully.');
    }

    public function updateOffers(Request $request, Shipment $shipment)
    {
        abort_unless($request->user()?->hasRole(['administrator', 'supervisor']), 403);

        $validated = $request->validate([
            'offered_carrier_ids' => 'nullable|array',
            'offered_carrier_ids.*' => 'uuid|exists:carriers,id',
        ]);

        if ($shipment->carrier_id) {
            $shipment->offeredCarriers()->sync([]);

            return redirect()->route('admin.shipments.show', $shipment)
                ->with('success', 'Offers were cleared because this shipment already has an assigned carrier.');
        }

        $offeredCarrierIds = collect($validated['offered_carrier_ids'] ?? [])
            ->map(fn ($carrierId) => (string) $carrierId)
            ->unique()
            ->values();

        $shipment->offeredCarriers()->sync(
            $this->buildOfferSyncPayload($shipment, $offeredCarrierIds->all(), $request->user()?->id)
        );

        return redirect()->route('admin.shipments.show', $shipment)
            ->with('success', 'Shipment offers updated successfully.');
    }

    public function updateConsolidation(Request $request, Shipment $shipment)
    {
        abort_unless($request->user()?->hasRole(['administrator', 'supervisor']), 403);

        $validated = $request->validate([
            'consolidated_shipment_ids' => 'nullable|array',
            'consolidated_shipment_ids.*' => 'uuid|exists:shipments,guid',
            'clear_consolidation' => 'nullable|boolean',
        ]);

        if (($validated['clear_consolidation'] ?? false) === true) {
            if (filled($shipment->consolidation_number)) {
                Shipment::query()
                    ->where('consolidation_number', $shipment->consolidation_number)
                    ->update(['consolidation_number' => null]);
            } else {
                $shipment->update(['consolidation_number' => null]);
            }

            return redirect()->route('admin.shipments.show', $shipment)
                ->with('success', 'Shipment consolidation removed successfully.');
        }

        $selectedGuids = collect($validated['consolidated_shipment_ids'] ?? [])
            ->map(fn (string $shipmentGuid): string => (string) $shipmentGuid)
            ->filter(fn (string $shipmentGuid): bool => $shipmentGuid !== $shipment->guid)
            ->unique()
            ->values();

        $selectedShipments = Shipment::query()
            ->whereIn('guid', $selectedGuids->all())
            ->where('pickup_location_id', $shipment->pickup_location_id)
            ->where('dc_location_id', $shipment->dc_location_id)
            ->get();

        if ($selectedShipments->count() !== $selectedGuids->count()) {
            throw ValidationException::withMessages([
                'consolidated_shipment_ids' => 'All selected shipments must have the same pickup and DC locations as this shipment.',
            ]);
        }

        if ($selectedShipments->isEmpty()) {
            if (filled($shipment->consolidation_number)) {
                Shipment::query()
                    ->where('consolidation_number', $shipment->consolidation_number)
                    ->update(['consolidation_number' => null]);
            } else {
                $shipment->update(['consolidation_number' => null]);
            }

            return redirect()->route('admin.shipments.show', $shipment)
                ->with('success', 'Shipment consolidation updated successfully.');
        }

        $consolidationNumber = filled($shipment->consolidation_number)
            ? (string) $shipment->consolidation_number
            : (string) Str::uuid();

        $targetShipmentIds = $selectedShipments
            ->pluck('id')
            ->push($shipment->getKey())
            ->unique()
            ->values();

        Shipment::query()
            ->whereIn('id', $targetShipmentIds->all())
            ->update([
                'consolidation_number' => $consolidationNumber,
                'carrier_id' => $shipment->carrier_id,
                'trailer_id' => $shipment->trailer_id,
                'loaned_from_trailer_id' => $shipment->loaned_from_trailer_id,
                'trailer' => $shipment->trailer,
            ]);

        Shipment::query()
            ->where('pickup_location_id', $shipment->pickup_location_id)
            ->where('dc_location_id', $shipment->dc_location_id)
            ->where('consolidation_number', $consolidationNumber)
            ->whereNotIn('id', $targetShipmentIds->all())
            ->update(['consolidation_number' => null]);

        return redirect()->route('admin.shipments.show', $shipment)
            ->with('success', 'Shipment consolidation updated successfully.');
    }

    public function destroy(Shipment $shipment)
    {
        $shipment->delete();

        return redirect()->route('admin.shipments.index')
            ->with('success', 'Shipment deleted successfully.');
    }

    /**
     * Quick update for carrier and/or trailer on a single shipment
     * Used for inline editing on Location Show page
     */
    public function quickUpdate(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'carrier_id' => 'nullable|uuid|exists:carriers,id',
            'trailer_id' => 'nullable|exists:trailers,id',
            'trailer_number' => 'nullable|string|max:100',
            'loaned_from_trailer_id' => 'nullable|exists:trailers,id',
        ]);

        $trailerNumber = trim((string) ($validated['trailer_number'] ?? ''));

        unset($validated['trailer_number']);

        if ($trailerNumber !== '') {
            if (blank($validated['carrier_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'carrier_id' => 'Select a carrier before assigning a trailer number.',
                ]);
            }

            $trailer = Trailer::query()
                ->where('carrier_id', $validated['carrier_id'])
                ->whereRaw('LOWER(number) = ?', [Str::lower($trailerNumber)])
                ->first();

            if (! $trailer) {
                $trailer = Trailer::create([
                    'guid' => (string) Str::uuid(),
                    'number' => $trailerNumber,
                    'carrier_id' => $validated['carrier_id'],
                    'status' => 'available',
                    'is_active' => true,
                ]);
            }

            $validated['trailer_id'] = $trailer->id;
        }

        $this->syncTrailerAssignments($shipment, $validated);

        $shipment->update($validated);

        if ($shipment->carrier_id) {
            $shipment->offeredCarriers()->sync([]);
        }

        $shipment->load(['trailer:id,number', 'loanedFromTrailer:id,number']);
        $trailer = $shipment->getRelation('trailer');

        return response()->json([
            'message' => 'Shipment updated successfully.',
            'shipment' => [
                'id' => $shipment->guid,
                'carrier_id' => $shipment->carrier_id,
                'trailer_id' => $shipment->trailer_id,
                'trailer_number' => $trailer?->number,
                'loaned_from_trailer_id' => $shipment->loaned_from_trailer_id,
            ],
        ]);
    }

    /**
     * Import shipments from Power BI XLSX file
     * - Skips first two rows
     * - Uses third row as headers
     * - Failed rows stored in session → downloaded via separate route
     */
    public function pbiImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            [$headerRow, $dataRows] = $this->extractPbiImportRows($worksheet->toArray());

            if ($headerRow === [] || $dataRows === []) {
                return back()->withErrors(['file' => 'No data found after the first two rows.']);
            }

            $imported = 0;
            $updated = 0;
            $failedRows = [];

            foreach ($dataRows as $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $mapped = $this->mapPbiImportRow($headerRow, $row);

                $originalRow = $row;

                $validator = Validator::make($mapped, [
                    'shipment_number' => ['required', 'string', 'max:100'],
                    'status' => ['required', 'string', 'max:50'],
                    'po_number' => ['nullable', 'string', 'max:100'],
                    'pickup_location' => ['required', 'string', 'max:50'],
                    'dc_location' => ['required', 'string', 'max:50'],
                    'pickup_date' => ['required'],
                    'delivery_date' => ['nullable'],
                    'rack_qty' => ['required', 'integer', 'min:0'],
                ]);

                if ($validator->fails()) {
                    $errorMsg = implode('; ', $validator->errors()->all());
                    $failedRows[] = array_merge($originalRow, ['ERROR' => $errorMsg]);

                    continue;
                }

                $validated = $validator->validated();

                try {
                    $pickupDateRaw = $this->parsePbiImportDate($validated['pickup_date'], 'Ship Date');
                } catch (Exception $exception) {
                    $failedRows[] = array_merge($originalRow, ['ERROR' => $exception->getMessage()]);

                    continue;
                }

                $deliveryDateRaw = null;
                if (filled($validated['delivery_date'] ?? null)) {
                    try {
                        $deliveryDateRaw = $this->parsePbiImportDate($validated['delivery_date'], 'Deliver Date');
                    } catch (Exception $exception) {
                        $failedRows[] = array_merge($originalRow, ['ERROR' => $exception->getMessage()]);

                        continue;
                    }
                }

                $pickup = Location::firstOrCreate(
                    ['short_code' => strtoupper($validated['pickup_location'])],
                    [
                        'guid' => \Str::uuid(),
                        'name' => strtoupper($validated['pickup_location']),
                        'address' => 'Unknown Address',
                        'city' => 'Unknown City',
                        'state' => 'Unknown State',
                        'zip' => '00000',
                        'country' => 'XX',
                        'is_active' => false,
                        'type' => 'pickup',
                    ]
                );

                $dc = Location::firstOrCreate(
                    ['short_code' => strtoupper($validated['dc_location'])],
                    [
                        'guid' => \Str::uuid(),
                        'name' => strtoupper($validated['dc_location']),
                        'address' => 'Unknown Address',
                        'city' => 'Unknown City',
                        'state' => 'Unknown State',
                        'zip' => '00000',
                        'country' => 'XX',
                        'expected_arrival_time' => '08:00:00',
                        'is_active' => false,
                        'type' => 'distribution_center',
                    ]
                );

                $time = $dc->expected_arrival_time
                    ? Carbon::parse($dc->expected_arrival_time)->format('H:i:s')
                    : '00:00:00';

                $pickupDate = $pickupDateRaw->format('Y-m-d').' '.$time;
                $deliveryDate = $deliveryDateRaw ? $deliveryDateRaw->format('Y-m-d').' '.$time : null;
                $equipmentDefaults = Shipment::defaultEquipmentCountsForRackQty((int) $validated['rack_qty']);

                $dropDate = Carbon::parse($pickupDate)->subDays(2);
                if ($dropDate->isSaturday()) {
                    $dropDate->subDay();
                } elseif ($dropDate->isSunday()) {
                    $dropDate->subDays(2);
                }

                $shipment = Shipment::firstOrNew(['shipment_number' => $validated['shipment_number']]);

                $wasExisting = $shipment->exists;

                $oldValues = $shipment->getAttributes();

                $googleSheetsProtectedFields = $wasExisting
                    ? $this->googleSheetsProtectedShipmentFields($shipment)
                    : [];

                $attributes = [
                    'shipment_number' => $validated['shipment_number'],
                    'status' => $validated['status'],
                    'po_number' => $validated['po_number'] ?? null,
                    'pickup_location_id' => $pickup->id,
                    'dc_location_id' => $dc->id,
                    'carrier_id' => null,
                    'drop_date' => $dropDate,
                    'pickup_date' => $pickupDate,
                    'delivery_date' => $deliveryDate,
                    'rack_qty' => (int) $validated['rack_qty'],
                    'load_bar_qty' => $equipmentDefaults['load_bar_qty'],
                    'strap_qty' => $equipmentDefaults['strap_qty'],
                ];

                if ($googleSheetsProtectedFields !== []) {
                    foreach ($googleSheetsProtectedFields as $field) {
                        unset($attributes[$field]);
                    }
                }

                $shipment->fill($attributes);

                if ($shipment->isDirty() || ! $wasExisting) {
                    $shipment->save();

                    $shipment->calculateBol();

                    if ($wasExisting) {
                        $this->recordImportUpdateNote($shipment, $oldValues, 'PBI import updated this shipment:');
                    }

                    $wasExisting ? $updated++ : $imported++;
                }
            }

            $message = "$imported new shipment(s) imported, $updated existing updated.";

            if ($failedRows !== []) {
                $message .= ' '.count($failedRows).' row(s) were skipped.';
            }

            return redirect()->route('admin.shipments.index')
                ->with('success', $message);

        } catch (Exception $exception) {
            return back()->withErrors(['file' => 'Failed to process file: '.$exception->getMessage()]);
        }
    }

    public function googleSheetsImport(Request $request)
    {
        abort_unless($request->user()?->hasRole(['administrator', 'supervisor']), 403);

        $validated = $request->validate([
            'google_sheet_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $googleSheetUrl = trim((string) ($validated['google_sheet_url'] ?? ''));

        if ($googleSheetUrl === '') {
            $googleSheetUrl = trim((string) AppSetting::getValue(AppSetting::GOOGLE_SHEET_URL_KEY));
        }

        if ($googleSheetUrl === '') {
            throw ValidationException::withMessages([
                'google_sheet_url' => 'Set a Google Sheets URL in App Settings or provide one for this import.',
            ]);
        }

        try {
            $exportUrl = $this->buildGoogleSheetsExportUrl($googleSheetUrl);

            $response = Http::timeout(20)->get($exportUrl);

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'google_sheet_url' => 'Failed to download the Google Sheet workbook.',
                ]);
            }

            $workbookContents = $response->body();

            if (
                str_contains($workbookContents, 'ServiceLogin')
                || str_contains($workbookContents, 'accounts.google.com')
                || str_contains(Str::lower((string) $response->header('Content-Type')), 'text/html')
            ) {
                throw ValidationException::withMessages([
                    'google_sheet_url' => 'The Google Sheet must be shared or published so the app can download it.',
                ]);
            }

            $sheetRows = $this->parseGoogleSheetsWorkbook($workbookContents);

            if ($sheetRows === []) {
                throw ValidationException::withMessages([
                    'google_sheet_url' => 'The Google Sheet workbook did not contain any readable sheet rows.',
                ]);
            }

            $updated = 0;
            $unchanged = 0;
            $failed = 0;
            $failedMessages = [];

            foreach ($sheetRows as [$headers, $rows]) {
                $previousGoogleSheetsRowContext = null;

                foreach ($rows as $row) {
                    if ($this->rowIsEmpty($row)) {
                        continue;
                    }

                    try {
                        $mappedRow = $this->mapGoogleSheetsRow($headers, $row);
                        [$mappedRow, $shouldConsolidateWithPrevious] = $this->resolveGoogleSheetsCarryForwardValues(
                            $mappedRow,
                            $previousGoogleSheetsRowContext
                        );

                        $shipment = $this->resolveShipmentForGoogleSheetsRow($mappedRow);

                        if (! $shipment) {
                            throw ValidationException::withMessages([
                                'google_sheet_url' => 'Each row must match an existing shipment by Shipment Number, Load, or BOL.',
                            ]);
                        }

                        $attributes = $this->buildGoogleSheetsShipmentAttributes($mappedRow, $shipment);

                        if ($shouldConsolidateWithPrevious) {
                            $attributes = $this->applyGoogleSheetsConsolidationAttributes(
                                $attributes,
                                $shipment,
                                $previousGoogleSheetsRowContext['shipment']
                            );
                        }

                        if ($attributes === []) {
                            $previousGoogleSheetsRowContext = $this->buildGoogleSheetsPreviousRowContext($shipment);
                            $unchanged++;

                            continue;
                        }

                        $oldValues = $shipment->getAttributes();

                        $this->syncTrailerAssignments($shipment, $attributes);

                        $shipment->fill($attributes);

                        if (! $shipment->isDirty()) {
                            $previousGoogleSheetsRowContext = $this->buildGoogleSheetsPreviousRowContext($shipment);
                            $unchanged++;

                            continue;
                        }

                        $shipment->save();

                        if ($shipment->carrier_id) {
                            $shipment->offeredCarriers()->sync([]);
                        }

                        $this->recordImportUpdateNote(
                            $shipment,
                            $oldValues,
                            'Google Sheets import updated this shipment:'
                        );

                        $previousGoogleSheetsRowContext = $this->buildGoogleSheetsPreviousRowContext($shipment);
                        $updated++;
                    } catch (ValidationException $exception) {
                        $failed++;
                        $failedMessages[] = collect($exception->errors())->flatten()->first();
                    } catch (Exception $exception) {
                        $failed++;
                        $failedMessages[] = $exception->getMessage();
                    }
                }
            }

            if ($updated === 0 && $failed > 0) {
                $firstFailureMessage = collect($failedMessages)
                    ->filter(fn ($message) => filled($message))
                    ->first();

                throw ValidationException::withMessages([
                    'google_sheet_url' => $firstFailureMessage
                        ? 'No shipment rows could be imported from the Google Sheet. '.$firstFailureMessage
                        : 'No shipment rows could be imported from the Google Sheet. Check the sheet headers, identifiers, and sharing settings.',
                ]);
            }

            $message = "$updated shipment(s) updated from Google Sheets.";

            if ($unchanged > 0) {
                $message .= " $unchanged row(s) had no changes.";
            }

            if ($failed > 0) {
                $message .= " $failed row(s) were skipped.";
            }

            return redirect()->route('admin.shipments.index')
                ->with('success', $message);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return back()->withErrors([
                'google_sheet_url' => 'Failed to process the Google Sheet: '.$exception->getMessage(),
            ]);
        }
    }

    private function buildGoogleSheetsExportUrl(string $url): string
    {
        if (! preg_match('#docs\.google\.com/spreadsheets/d/([^/]+)#', $url, $matches)) {
            throw ValidationException::withMessages([
                'google_sheet_url' => 'Enter a valid Google Sheets URL.',
            ]);
        }

        $spreadsheetId = $matches[1];

        return 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId.'/export?format=xlsx';
    }

    /**
     * @param  array<string, string>  $mappedRow
     * @param  array{shipment: Shipment, trailer: string, load_bar_qty: int, strap_qty: int}|null  $previousRowContext
     * @return array{0: array<string, string>, 1: bool}
     */
    private function resolveGoogleSheetsCarryForwardValues(array $mappedRow, ?array $previousRowContext): array
    {
        $shouldConsolidateWithPrevious = $this->isGoogleSheetsCarryForwardMarker($mappedRow['trailer'] ?? null);

        if ($shouldConsolidateWithPrevious) {
            if ($previousRowContext === null) {
                throw ValidationException::withMessages([
                    'google_sheet_url' => 'Trailer "^" requires a shipment row immediately above it on the same sheet.',
                ]);
            }

            $mappedRow['trailer'] = $previousRowContext['trailer'];
        }

        if ($this->isGoogleSheetsCarryForwardMarker($mappedRow['load_bar_qty'] ?? null)) {
            if ($previousRowContext === null) {
                throw ValidationException::withMessages([
                    'google_sheet_url' => 'Load bars "^" requires a shipment row immediately above it on the same sheet.',
                ]);
            }

            $mappedRow['load_bar_qty'] = (string) $previousRowContext['load_bar_qty'];
        }

        if ($this->isGoogleSheetsCarryForwardMarker($mappedRow['strap_qty'] ?? null)) {
            if ($previousRowContext === null) {
                throw ValidationException::withMessages([
                    'google_sheet_url' => 'Straps "^" requires a shipment row immediately above it on the same sheet.',
                ]);
            }

            $mappedRow['strap_qty'] = (string) $previousRowContext['strap_qty'];
        }

        return [$mappedRow, $shouldConsolidateWithPrevious];
    }

    private function isGoogleSheetsCarryForwardMarker(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return str_contains(trim($value), '^');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: array<int, mixed>, 1: array<int, array<int, mixed>>}
     */
    private function extractPbiImportRows(array $rows): array
    {
        $headerRow = $rows[2] ?? [];

        if ($headerRow === [] || $this->rowIsEmpty($headerRow)) {
            return [[], []];
        }

        return [$headerRow, array_slice($rows, 3)];
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapPbiImportRow(array $headerRow, array $row): array
    {
        $mapped = [];

        foreach ($row as $columnIndex => $value) {
            $header = $this->canonicalPbiImportHeader((string) ($headerRow[$columnIndex] ?? ''));

            if (! $header) {
                continue;
            }

            $mapped[$header] = is_string($value) ? trim($value) : $value;
        }

        return $mapped;
    }

    private function canonicalPbiImportHeader(string $header): ?string
    {
        $normalizedHeader = Str::of($header)
            ->replace("\u{FEFF}", '')
            ->lower()
            ->replace(['#', '/', '-', '(', ')'], ' ')
            ->replaceMatches('/[^a-z0-9\s]/', '')
            ->squish()
            ->value();

        return match ($normalizedHeader) {
            'load', 'shipment number' => 'shipment_number',
            'status' => 'status',
            'msft po', 'msft po number', 'order', 'order number' => 'po_number',
            'origin' => 'pickup_location',
            'destination' => 'dc_location',
            'ship date' => 'pickup_date',
            'deliver date' => 'delivery_date',
            'sum of pallets', 'rack qty', 'rack quantity' => 'rack_qty',
            default => null,
        };
    }

    private function parsePbiImportDate(mixed $value, string $field): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            throw new Exception("Invalid '{$field}' format.");
        }

        if (is_numeric($normalizedValue)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $normalizedValue));
            } catch (Exception) {
                // Fall through to string-based parsing.
            }
        }

        foreach (['m/d/Y', 'n/j/Y', 'm/d/y', 'n/j/y', 'm/d/Y H:i', 'n/j/Y H:i', 'm/d/Y g:i A', 'n/j/Y g:i A', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $normalizedValue);
            } catch (Exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($normalizedValue);
        } catch (Exception) {
            throw new Exception("Invalid '{$field}' format.");
        }
    }

    /**
     * @return array<int, array{0: array<int, string>, 1: array<int, array<int, string|null>>}>
     */
    private function parseGoogleSheetsWorkbook(string $workbookContents): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'google-sheets-import-');

        if ($tempPath === false) {
            throw new Exception('Unable to create temporary workbook file.');
        }

        file_put_contents($tempPath, $workbookContents);

        try {
            $spreadsheet = IOFactory::load($tempPath);
        } catch (Exception $exception) {
            @unlink($tempPath);

            throw new Exception('Unable to read the Google Sheet workbook.');
        }

        @unlink($tempPath);

        $sheetRows = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = $worksheet->toArray(null, true, true, false);

            if ($rows === []) {
                continue;
            }

            $headerRow = array_shift($rows);

            if (! is_array($headerRow) || $this->rowIsEmpty($headerRow)) {
                continue;
            }

            $sheetRows[] = [
                array_map(fn ($header) => trim((string) $header), $headerRow),
                array_map(fn ($row) => is_array($row) ? array_map(fn ($value) => $value === null ? null : (string) $value, $row) : [], $rows),
            ];
        }

        return $sheetRows;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function mapGoogleSheetsRow(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $canonicalHeader = $this->canonicalGoogleSheetsHeader($header);

            if (! $canonicalHeader) {
                continue;
            }

            $mapped[$canonicalHeader] = trim((string) ($row[$index] ?? ''));
        }

        return $mapped;
    }

    private function canonicalGoogleSheetsHeader(string $header): ?string
    {
        $normalizedHeader = Str::of($header)
            ->replace("\u{FEFF}", '')
            ->lower()
            ->replace(['#', '/', '-', '(', ')'], ' ')
            ->replaceMatches('/[^a-z0-9\s]/', '')
            ->squish()
            ->value();

        return match ($normalizedHeader) {
            'shipment number', 'shipment', 'load', 'load number', 'reference' => 'shipment_number',
            'bol', 'bol number' => 'bol',
            'status' => 'status',
            'po', 'po number', 'po number msft', 'msft po', 'msft po number' => 'po_number',
            'origin', 'pickup', 'pickup location', 'pickup code', 'pickup location code', 'shipper', 'shipper code' => 'pickup_location',
            'destination', 'dc', 'dc location', 'dc code', 'destination code' => 'dc_location',
            'carrier', 'carrier name', 'carrier code', 'carrier short code' => 'carrier',
            'trailer', 'trailer number' => 'trailer',
            'seal', 'seal number' => 'seal_number',
            'driver', 'driver id', 'drivers id' => 'drivers_id',
            'drop date' => 'drop_date',
            'pickup date', 'ship date' => 'pickup_date',
            'delivery date', 'deliver date' => 'delivery_date',
            'sum of pallets', 'pallets', 'rack qty', 'rack quantity' => 'rack_qty',
            'load bars', 'load bar qty', 'load bar quantity' => 'load_bar_qty',
            'straps', 'strap qty', 'strap quantity' => 'strap_qty',
            default => null,
        };
    }

    private function resolveShipmentForGoogleSheetsRow(array $mappedRow): ?Shipment
    {
        $shipmentNumber = trim((string) ($mappedRow['shipment_number'] ?? ''));

        if ($shipmentNumber !== '') {
            return Shipment::query()
                ->whereRaw('LOWER(shipment_number) = ?', [Str::lower($shipmentNumber)])
                ->first();
        }

        $bol = trim((string) ($mappedRow['bol'] ?? ''));

        if ($bol !== '') {
            return Shipment::query()
                ->whereRaw('LOWER(bol) = ?', [Str::lower($bol)])
                ->first();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGoogleSheetsShipmentAttributes(array $mappedRow, Shipment $shipment): array
    {
        $attributes = [];

        foreach (['shipment_number', 'bol', 'status', 'po_number', 'trailer', 'seal_number', 'drivers_id'] as $field) {
            if (($mappedRow[$field] ?? '') !== '') {
                $attributes[$field] = $mappedRow[$field];
            }
        }

        if (($mappedRow['pickup_location'] ?? '') !== '') {
            $attributes['pickup_location_id'] = $this->resolveLocationForGoogleSheetsImport($mappedRow['pickup_location'], 'pickup')->id;
        }

        if (($mappedRow['dc_location'] ?? '') !== '') {
            $attributes['dc_location_id'] = $this->resolveLocationForGoogleSheetsImport($mappedRow['dc_location'], 'distribution_center')->id;
        }

        if (($mappedRow['carrier'] ?? '') !== '') {
            $attributes['carrier_id'] = $this->resolveCarrierForGoogleSheetsImport($mappedRow['carrier'])->id;
        }

        if (($mappedRow['trailer'] ?? '') !== '') {
            $carrierId = $attributes['carrier_id'] ?? $shipment->carrier_id;

            if (! blank($carrierId)) {
                $trailerNumber = trim((string) $mappedRow['trailer']);

                $trailer = Trailer::query()
                    ->where('carrier_id', $carrierId)
                    ->whereRaw('LOWER(number) = ?', [Str::lower($trailerNumber)])
                    ->first();

                if (! $trailer) {
                    $trailer = Trailer::create([
                        'guid' => (string) Str::uuid(),
                        'number' => $trailerNumber,
                        'carrier_id' => $carrierId,
                        'status' => 'available',
                        'is_active' => true,
                    ]);
                }

                $attributes['trailer_id'] = $trailer->id;
            }
        }

        if (($mappedRow['drop_date'] ?? '') !== '') {
            $attributes['drop_date'] = $this->parseGoogleSheetsDate($mappedRow['drop_date'])?->toDateString();
        }

        if (($mappedRow['pickup_date'] ?? '') !== '') {
            $attributes['pickup_date'] = $this->parseGoogleSheetsDate($mappedRow['pickup_date'])?->format('Y-m-d H:i:s');
        }

        if (($mappedRow['delivery_date'] ?? '') !== '') {
            $attributes['delivery_date'] = $this->parseGoogleSheetsDate($mappedRow['delivery_date'])?->format('Y-m-d H:i:s');
        }

        if (($mappedRow['rack_qty'] ?? '') !== '') {
            $rackQty = (int) $mappedRow['rack_qty'];
            $attributes['rack_qty'] = $rackQty;

            $equipmentDefaults = Shipment::defaultEquipmentCountsForRackQty($rackQty);
            $attributes['load_bar_qty'] = ($mappedRow['load_bar_qty'] ?? '') !== ''
                ? (int) $mappedRow['load_bar_qty']
                : $equipmentDefaults['load_bar_qty'];
            $attributes['strap_qty'] = ($mappedRow['strap_qty'] ?? '') !== ''
                ? (int) $mappedRow['strap_qty']
                : $equipmentDefaults['strap_qty'];
        } else {
            if (($mappedRow['load_bar_qty'] ?? '') !== '') {
                $attributes['load_bar_qty'] = (int) $mappedRow['load_bar_qty'];
            }

            if (($mappedRow['strap_qty'] ?? '') !== '') {
                $attributes['strap_qty'] = (int) $mappedRow['strap_qty'];
            }
        }

        if (array_key_exists('carrier_id', $attributes) && blank($attributes['carrier_id'])) {
            unset($attributes['carrier_id']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyGoogleSheetsConsolidationAttributes(array $attributes, Shipment $shipment, Shipment $previousShipment): array
    {
        $currentPickupLocationId = $attributes['pickup_location_id'] ?? $shipment->pickup_location_id;
        $currentDcLocationId = $attributes['dc_location_id'] ?? $shipment->dc_location_id;

        if (
            $currentPickupLocationId !== $previousShipment->pickup_location_id
            || $currentDcLocationId !== $previousShipment->dc_location_id
        ) {
            throw ValidationException::withMessages([
                'google_sheet_url' => 'Trailer "^" can only consolidate shipments that have the same pickup location and the same DC location as the row above it.',
            ]);
        }

        $consolidationNumber = $previousShipment->consolidation_number;

        if (blank($consolidationNumber)) {
            $consolidationNumber = (string) Str::uuid();

            $previousShipment->forceFill([
                'consolidation_number' => $consolidationNumber,
            ])->saveQuietly();
        }

        $attributes['consolidation_number'] = $consolidationNumber;
        $attributes['carrier_id'] = $previousShipment->carrier_id;
        $attributes['trailer_id'] = $previousShipment->trailer_id;
        $attributes['loaned_from_trailer_id'] = $previousShipment->loaned_from_trailer_id;
        $attributes['trailer'] = $previousShipment->trailer;

        return $attributes;
    }

    /**
     * @return array{shipment: Shipment, trailer: string, load_bar_qty: int, strap_qty: int}
     */
    private function buildGoogleSheetsPreviousRowContext(Shipment $shipment): array
    {
        return [
            'shipment' => $shipment,
            'trailer' => (string) ($shipment->trailer ?? ''),
            'load_bar_qty' => (int) ($shipment->load_bar_qty ?? 0),
            'strap_qty' => (int) ($shipment->strap_qty ?? 0),
        ];
    }

    private function resolveLocationForGoogleSheetsImport(string $value, string $type): Location
    {
        $normalizedValue = trim($value);

        $location = Location::query()
            ->where(function ($query) use ($normalizedValue) {
                $query->whereRaw('LOWER(short_code) = ?', [Str::lower($normalizedValue)])
                    ->orWhereRaw('LOWER(name) = ?', [Str::lower($normalizedValue)]);
            })
            ->first();

        if ($location) {
            return $location;
        }

        return Location::query()->forceCreate([
            'guid' => (string) Str::uuid(),
            'short_code' => Str::upper(Str::limit($normalizedValue, 20, '')),
            'name' => $normalizedValue,
            'address' => 'Unknown Address',
            'city' => 'Unknown',
            'state' => 'NA',
            'zip' => '00000',
            'country' => 'XX',
            'expected_arrival_time' => $type === 'distribution_center' ? '08:00:00' : null,
            'is_active' => false,
            'type' => $type,
        ]);
    }

    private function resolveCarrierForGoogleSheetsImport(string $value): Carrier
    {
        $normalizedValue = trim($value);
        $uppercasedValue = strtoupper($normalizedValue);

        $carrier = Carrier::query()
            ->where(function ($query) use ($normalizedValue, $uppercasedValue) {
                $query->whereRaw('LOWER(name) = ?', [Str::lower($normalizedValue)])
                    ->orWhereRaw('UPPER(short_code) = ?', [$uppercasedValue]);
            })
            ->first();

        if (! $carrier) {
            throw ValidationException::withMessages([
                'google_sheet_url' => "Carrier [{$normalizedValue}] could not be resolved.",
            ]);
        }

        return $carrier;
    }

    private function parseGoogleSheetsDate(string $value): ?Carbon
    {
        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            return null;
        }

        foreach ([
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/Y',
            'n/j/Y H:i',
            'n/j/Y g:i A',
            'n/j/Y',
        ] as $format) {
            try {
                return Carbon::createFromFormat($format, $normalizedValue);
            } catch (Exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($normalizedValue);
        } catch (Exception) {
            throw ValidationException::withMessages([
                'google_sheet_url' => "Date value [{$normalizedValue}] could not be parsed.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $oldValues
     */
    private function recordImportUpdateNote(Shipment $shipment, array $oldValues, string $contentPrefix): void
    {
        $trackedFields = $this->shipmentImportTrackedFieldLabels();

        $changes = [];

        foreach ($trackedFields as $field => $label) {
            $old = $oldValues[$field] ?? null;
            $new = $shipment->{$field};

            if (in_array($field, ['drop_date', 'pickup_date', 'delivery_date'], true)) {
                $old = $old ? Carbon::parse($old)->format('Y-m-d H:i:s') : null;
                $new = $new ? Carbon::parse($new)->format('Y-m-d H:i:s') : null;
            } elseif (in_array($field, ['pickup_location_id', 'dc_location_id'], true)) {
                $old = Location::find($old)?->short_code;
                $new = Location::find($new)?->short_code;
            } elseif ($field === 'carrier_id') {
                $old = Carrier::find($old)?->name;
                $new = Carrier::find($new)?->name;
            }

            if ($old !== $new) {
                $changes[] = "$label changed from '{$old}' to '{$new}'";
            }
        }

        if ($changes === []) {
            return;
        }

        $shipment->notes()->create([
            'content' => $contentPrefix."\n".implode("\n", $changes),
            'is_admin' => false,
            'user_id' => auth()->id() ?? null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function googleSheetsProtectedShipmentFields(Shipment $shipment): array
    {
        $labelToField = array_flip($this->shipmentImportTrackedFieldLabels());

        $protectedFields = [];

        $notes = $shipment->notes()
            ->where('content', 'like', 'Google Sheets import updated this shipment:%')
            ->latest('id')
            ->get(['content']);

        foreach ($notes as $note) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $note->content) as $line) {
                if (! is_string($line) || trim($line) === '') {
                    continue;
                }

                if (! preg_match('/^(.+?) changed from /', trim($line), $matches)) {
                    continue;
                }

                $label = trim($matches[1]);
                $field = $labelToField[$label] ?? null;

                if ($field) {
                    $protectedFields[$field] = $field;
                }
            }
        }

        return array_values($protectedFields);
    }

    /**
     * @return array<string, string>
     */
    private function shipmentImportTrackedFieldLabels(): array
    {
        return [
            'status' => 'Status',
            'po_number' => 'PO Number',
            'pickup_location_id' => 'Pickup Location',
            'dc_location_id' => 'DC Location',
            'carrier_id' => 'Carrier',
            'drop_date' => 'Drop Date',
            'pickup_date' => 'Pickup Date',
            'delivery_date' => 'Delivery Date',
            'rack_qty' => 'Pallets/Rack Qty',
            'load_bar_qty' => 'Load Bar Qty',
            'strap_qty' => 'Strap Qty',
            'trailer' => 'Trailer',
            'seal_number' => 'Seal Number',
            'drivers_id' => 'Drivers ID',
        ];
    }

    protected function resolveLocationIdByGuid(?string $guid): string|int|null
    {
        if (blank($guid)) {
            return null;
        }

        return Location::query()
            ->where('guid', $guid)
            ->value('id');
    }

    protected function formatDateValue(mixed $value, string $format): ?string
    {
        if (! $value instanceof Carbon) {
            return null;
        }

        return $value->format($format);
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    /**
     * Download the failed rows TSV (one-time use)
     */
    public function downloadFailedTsv()
    {
        $content = session('failed_tsv_content');
        $filename = session('failed_tsv_filename', 'failed_shipments.tsv');

        if (! $content) {
            return redirect()->route('admin.shipments.index')
                ->with('error', 'No failed file available to download.');
        }

        // Clear session data after serving
        session()->forget(['failed_tsv_content', 'failed_tsv_filename']);

        return response($content)
            ->header('Content-Type', 'text/tab-separated-values; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }

    public function sendPaperwork(Request $request, Shipment $shipment): \Inertia\Response
    {
        $shipment->load(['pickupLocation:id,short_code,name', 'dcLocation:id,short_code,name', 'carrier:id,name']);

        $templates = Template::where('model_type', 'App\Models\Location')
            ->where('model_id', $shipment->pickup_location_id)
            ->get();

        return Inertia::render('Admin/Shipments/SendPaperwork', [
            'shipment' => [
                'id' => $shipment->guid,
                'shipment_number' => $shipment->shipment_number,
                'bol' => $shipment->bol,
                'po_number' => $shipment->po_number,
                'status' => $shipment->status,
                'pickup_location' => $shipment->pickupLocation ? [
                    'id' => $shipment->pickupLocation->guid,
                    'short_code' => $shipment->pickupLocation->short_code,
                    'name' => $shipment->pickupLocation->name,
                ] : null,
                'dc_location' => $shipment->dcLocation ? [
                    'id' => $shipment->dcLocation->guid,
                    'short_code' => $shipment->dcLocation->short_code,
                    'name' => $shipment->dcLocation->name,
                ] : null,
                'carrier' => $shipment->carrier ? [
                    'name' => $shipment->carrier->name,
                ] : null,
                'drop_date' => $shipment->drop_date,
                'pickup_date' => $shipment->pickup_date,
                'delivery_date' => $shipment->delivery_date,
                'rack_qty' => $shipment->rack_qty,
                'load_bar_qty' => $shipment->load_bar_qty,
                'strap_qty' => $shipment->strap_qty,
                'trailer' => $shipment->trailer,
                'drayage' => $shipment->drayage,
                'on_site' => $shipment->on_site,
                'shipped' => $shipment->shipped,
                'recycling_sent' => $shipment->recycling_sent,
                'paperwork_sent' => $shipment->paperwork_sent,
                'delivery_alert_sent' => $shipment->delivery_alert_sent,
                'created_at' => $shipment->created_at,
                'updated_at' => $shipment->updated_at,
            ],
            'templates' => $templates,
        ]);
    }

    public function generateTable(Shipment $shipment, array $columns)
    {
        $html = <<<'HTML'
<table style="border-collapse: collapse; width: 100%; border-width: 1px; border-color: #000000;" border="1">
    <tbody>
        <tr style="background-color: #0b5394;color: #ecf0f1;text-align: center;">
HTML;

        foreach ($columns as $column) {
            switch (strtolower(trim($column))) {
                case 'status':
                    $html .= '<td><strong>Status</strong></td>';
                    break;
                case 'bol':
                    $html .= '<td><strong>BOL</strong></td>';
                    break;
                case 'pickup_location':
                    $html .= '<td><strong>Pickup Location</strong></td>';
                    break;
                case 'shipment_number':
                    $html .= '<td><strong>Shipment Number</strong></td>';
                    break;
                case 'dc_location':
                    $html .= '<td><strong>DC Location</strong></td>';
                    break;
                case 'drop_date':
                    $html .= '<td><strong>Drop Date</strong></td>';
                    break;
                case 'pickup_date':
                    $html .= '<td><strong>Pickup Date</strong></td>';
                    break;
                case 'delivery_date':
                    $html .= '<td><strong>Delivery Date</strong></td>';
                    break;
                case 'po_number':
                    $html .= '<td><strong>PO</strong></td>';
                    break;
                case 'rack_qty':
                    $html .= '<td><strong>Rack Qty</strong></td>';
                    break;
                case 'carrier_code':
                    $html .= '<td><strong>Carrier</strong></td>';
                    break;
                case 'trailer':
                    $html .= '<td><strong>Trailer</strong></td>';
                    break;
                case 'load_bar_qty':
                    $html .= '<td><strong>Load Bars</strong></td>';
                    break;
                case 'strap_qty':
                    $html .= '<td><strong>Straps</strong></td>';
                    break;
                case 'dc_location_address':
                    $html .= '<td><strong>Delivery Address</strong></td>';
                    break;
                default:
                    // Ignore unknown columns
                    break;
            }
        }
        $html .= <<<'HTML'
        </tr>
        <tr style="border-color: #000000; background-color: #ffffff; color: #000000;">
HTML;
        if ($shipment->isConsolidation()) {
            $shipment->load('consolidationShipments');
            $shipments = $shipment->consolidationShipments;
            foreach ($shipments as $consolShipment) {
                foreach ($columns as $column) {
                    switch (strtolower(trim($column))) {
                        case 'status':
                            $html .= '<td>'.($consolShipment->status ?? '').'</td>';
                            break;
                        case 'bol':
                            $html .= '<td>'.($consolShipment->bol ?? '').'</td>';
                            break;
                        case 'pickup_location':
                            $html .= '<td>'.(optional($consolShipment->pickupLocation)->short_code ?? '').'</td>';
                            break;
                        case 'shipment_number':
                            $html .= '<td>'.($consolShipment->shipment_number ?? '').'</td>';
                            break;
                        case 'dc_location':
                            $html .= '<td>'.(optional($consolShipment->dcLocation)->short_code ?? '').'</td>';
                            break;
                        case 'drop_date':
                            $html .= '<td>'.(Carbon::parse($consolShipment->drop_date)->format('m/d/Y') ?? '').'</td>';
                            break;
                        case 'pickup_date':
                            $html .= '<td>'.(Carbon::parse($consolShipment->pickup_date)->format('m/d/Y') ?? '').'</td>';
                            break;
                        case 'delivery_date':
                            $html .= '<td>'.(Carbon::parse($consolShipment->delivery_date)->format('m/d/Y') ?? '').'</td>';
                            break;
                        case 'po_number':
                            $html .= '<td>'.($consolShipment->po_number ?? '').'</td>';
                            break;
                        case 'rack_qty':
                            $html .= '<td>'.($consolShipment->rack_qty ?? '').'</td>';
                            break;
                        case 'carrier_code':
                            $html .= '<td>'.(optional($consolShipment->carrier)->short_code ?? '').'</td>';
                            break;
                        case 'trailer':
                            $html .= '<td>'.($consolShipment->trailer ?? '').'</td>';
                            break;
                        case 'load_bar_qty':
                            $html .= '<td>'.($consolShipment->load_bar_qty ?? '').'</td>';
                            break;
                        case 'strap_qty':
                            $html .= '<td>'.($consolShipment->strap_qty ?? '').'</td>';
                            break;
                        case 'dc_location_address':
                            $html .= '<td>'.(optional($consolShipment->dcLocation)->address ?? '').'</td>';
                            break;
                        default:
                            // Ignore unknown columns
                            break;
                    }
                }
            }

        }
        $html .= <<<'HTML'
        </tr>
    </tbody>
</table>
HTML;

        $shipment->load(['pickupLocation', 'dcLocation', 'carrier']);

        $tableHtml = view('shipments.partials.msft_table_1', ['shipment' => $shipment])->render();

        return response()->json(['table_html' => $tableHtml]);
    }

    /**
     * Process SendPaperwork form and send the selected template to carrier emails.
     */
    public function processSendPaperwork(Request $request, Shipment $shipment): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'template_id' => 'required|exists:templates,id',
            'lrc_file' => 'nullable|file|mimes:pdf|max:10240', // 10MB max, PDF only
            'bol_file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $template = Template::findOrFail($data['template_id']);

        $shipment->load(['pickupLocation', 'dcLocation', 'carrier']);

        $replacements = [
            'table' => '',
            'status' => $shipment->status ?? '',
            'shipment_number' => $shipment->shipment_number ?? 'XXXX',
            'bol' => $shipment->bol ?? 'XXXX',
            'po_number' => $shipment->po_number ?? '',
            'pickup_location' => optional($shipment->pickupLocation)->short_code ?? '',
            'pickup_location_name' => optional($shipment->pickupLocation)->name ?? '',
            'dc_location' => optional($shipment->dcLocation)->short_code ?? '',
            'dc_location_name' => optional($shipment->dcLocation)->name ?? '',
            'dc_location_address' => optional($shipment->dcLocation)->address ?? '',
            'carrier_code' => optional($shipment->carrier)->short_code ?? '',
            'trailer' => $shipment->trailer ?? 'XXXX',
            'load_bar_qty' => $shipment->load_bar_qty ?? '0',
            'rack_qty' => $shipment->rack_qty ?? '0',
            'strap_qty' => $shipment->strap_qty ?? '0',
            'drop_date' => $shipment->drop_date ? Carbon::parse($shipment->drop_date)->format('m/d/Y') : '',
            'pickup_date' => $shipment->pickup_date ? Carbon::parse($shipment->pickup_date)->format('m/d/Y') : '',
            'delivery_date' => $shipment->delivery_date ? Carbon::parse($shipment->delivery_date)->format('m/d/Y') : '',
        ];

        $renderPlaceholders = function (string $text) use ($replacements): string {
            return preg_replace_callback('/\{\{?\s*([^\}\s]+)\s*\}?\}/', function ($matches) use ($replacements) {
                $key = strtolower(trim($matches[1]));

                return $replacements[$key] ?? $matches[0];
            }, $text);
        };

        $subject = $renderPlaceholders((string) $template->subject);
        $body = $renderPlaceholders((string) $template->message);

        $recipients = [];

        $carrier = $shipment->carrier;

        if ($carrier && isset($carrier->emails) && ! empty($carrier->emails)) {
            $input = str_replace(',', ';', $carrier->emails);
            $parts = array_map('trim', explode(';', $input));
            $emails = [];

            foreach ($parts as $part) {
                if (empty($part)) {
                    continue;
                }

                // "Name" <email@domain.com>
                if (preg_match('/<([^>]+)>/', $part, $matches)) {
                    $email = trim($matches[1]);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $email;
                    }
                }
                // Plain email
                elseif (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $part;
                }
            }

            $recipients = array_values(array_unique($emails));
        }

        if (empty($recipients)) {
            return back()->withErrors(['recipients' => 'No valid recipient emails found for the shipment carrier.']);
        }

        try {
            Mail::send([], [], function ($message) use ($recipients, $subject, $body, $request) {
                $message->to($recipients);
                $message->subject($subject);
                $message->html($body);

                // Attach LRC file if uploaded
                if ($request->hasFile('lrc_file') && $request->file('lrc_file')->isValid()) {
                    $message->attach(
                        $request->file('lrc_file')->getRealPath(),
                        [
                            'as' => $request->file('lrc_file')->getClientOriginalName(),
                            'mime' => $request->file('lrc_file')->getMimeType(),
                        ]
                    );
                }

                // Attach BOL file if uploaded
                if ($request->hasFile('bol_file') && $request->file('bol_file')->isValid()) {
                    $message->attach(
                        $request->file('bol_file')->getRealPath(),
                        [
                            'as' => $request->file('bol_file')->getClientOriginalName(),
                            'mime' => $request->file('bol_file')->getMimeType(),
                        ]
                    );
                }
            });

            if ($shipment->isFillable('paperwork_sent')) {
                $shipment->update(['paperwork_sent' => now()]);
            }

            return redirect()->route('admin.shipments.show', $shipment)
                ->with('success', 'Paperwork sent successfully.');
        } catch (\Exception $e) {
            \Log::error('Paperwork email failed: '.$e->getMessage());

            return back()->withErrors(['email' => 'Failed to send email: '.$e->getMessage()]);
        }
    }

    public function calculateBol(Shipment $shipment): \Illuminate\Http\RedirectResponse
    {
        try {
            $shipment->calculateBol();

            return redirect()->route('admin.shipments.show', $shipment)
                ->with('success', 'BOL calculated successfully.');
        } catch (\Exception $e) {
            \Log::error("Failed to calculate BOL for shipment {$shipment->id}: ".$e->getMessage());

            return back()->withErrors(['bol' => 'Failed to calculate BOL: '.$e->getMessage()]);
        }
    }
}
