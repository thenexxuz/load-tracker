<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const DASHBOARD_SECTION_KEYS = [
        'booked_shipments',
        'deliveries_chart',
        'monitored_locations',
        'active_shipments_by_carrier',
        'shipment_offers_by_user',
    ];

    public function index(): Response
    {
        $user = auth()->user();
        $isAdminOrSupervisor = $user->hasAnyRole(['administrator', 'supervisor']);
        $dashboardPreferences = $this->dashboardPreferencesFor($user);

        $data = [
            'isAdminOrSupervisor' => $isAdminOrSupervisor,
            'dashboardPreferences' => $dashboardPreferences,
        ];

        if ($isAdminOrSupervisor) {
            $availableMonitoredLocations = $this->availableMonitoredLocations();
            $monitoredLocations = $this->resolveMonitoredLocations(collect($dashboardPreferences['monitored_location_ids']));

            // Booked count (simple number)
            $bookedCount = Shipment::query()
                ->whereRaw('LOWER(status) = ?', ['booked'])
                ->count();

            // Deliveries per day – last 30 days
            $startDate = now()->subDays(29)->startOfDay();
            $endDate = now()->endOfDay();

            $dailyDeliveries = Shipment::query()
                ->whereRaw('LOWER(status) = ?', ['delivered'])
                ->whereBetween('delivery_date', [$startDate, $endDate])
                ->selectRaw('DATE(delivery_date) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date')
                ->toArray();

            // Fill missing days with 0
            $labels = [];
            $values = [];
            for ($i = 0; $i < 30; $i++) {
                $date = $startDate->copy()->addDays($i)->format('Y-m-d');
                $labels[] = $date;
                $values[] = $dailyDeliveries[$date] ?? 0;
            }

            $data['chartData'] = [
                'labels' => $labels,
                'values' => $values,
            ];

            $data['bookedCount'] = $bookedCount;
            $data['availableMonitoredLocations'] = $availableMonitoredLocations;

            [$lastWeekStart, $lastWeekEnd] = $this->lastCalendarWeekRange();

            $data['monitoredLocationShipmentSummary'] = $this->monitoredLocationShipmentSummary($monitoredLocations);
            $data['carrierActiveShipmentSummary'] = $this->carrierActiveShipmentSummary();
            $data['offerActivitySummary'] = [
                'week' => [
                    'start' => $lastWeekStart->toDateString(),
                    'end' => $lastWeekEnd->toDateString(),
                    'label' => sprintf('%s - %s', $lastWeekStart->format('M j'), $lastWeekEnd->format('M j, Y')),
                ],
                'users' => $this->offerActivitySummary($lastWeekStart, $lastWeekEnd),
            ];
        }

        return Inertia::render('Dashboard', $data);
    }

    public function editPreferences(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Dashboard', [
            'dashboardPreferences' => $this->dashboardPreferencesFor($user),
            'availableMonitoredLocations' => $this->availableMonitoredLocations(),
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'sections' => ['nullable', 'array'],
            'sections.booked_shipments' => ['nullable', 'boolean'],
            'sections.deliveries_chart' => ['nullable', 'boolean'],
            'sections.monitored_locations' => ['nullable', 'boolean'],
            'sections.active_shipments_by_carrier' => ['nullable', 'boolean'],
            'sections.shipment_offers_by_user' => ['nullable', 'boolean'],
            'monitored_location_ids' => ['nullable', 'array'],
            'monitored_location_ids.*' => ['uuid', 'exists:locations,guid'],
        ]);

        $current = $this->dashboardPreferencesFor($user);

        $incomingSections = collect($validated['sections'] ?? []);
        $sections = collect(self::DASHBOARD_SECTION_KEYS)
            ->mapWithKeys(fn (string $key): array => [
                $key => (bool) $incomingSections->get($key, $current['sections'][$key] ?? true),
            ])
            ->all();

        $defaultMonitoredLocationIds = Location::query()
            ->where('type', 'pickup')
            ->pluck('guid')
            ->filter()
            ->values()
            ->all();

        $monitoredLocationIds = collect($validated['monitored_location_ids'] ?? $current['monitored_location_ids'] ?? $defaultMonitoredLocationIds)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $user->forceFill([
            'dashboard_preferences' => [
                'sections' => $sections,
                'monitored_location_ids' => $monitoredLocationIds,
            ],
        ])->save();

        return redirect()->route('dashboard-preferences.edit')
            ->with('success', 'Dashboard preferences saved.');
    }

    /**
     * @return array<int, array{id:int, name:string, short_code:?string, shipment_count:int, unassigned_shipment_count:int, status_breakdown:array<int, array{status:string, count:int}>}>
     */
    private function monitoredLocationShipmentSummary(Collection $monitoredLocations): array
    {
        $allPickupCodes = Location::query()
            ->where('type', 'pickup')
            ->pluck('short_code')
            ->filter()
            ->sort()
            ->values();

        $allDcCodes = Location::query()
            ->whereIn('type', ['distribution_center', 'pickup'])
            ->pluck('short_code')
            ->filter()
            ->sort()
            ->values();

        $allStatuses = Shipment::query()
            ->distinct('status')
            ->pluck('status')
            ->filter()
            ->sort()
            ->values();

        $outboundLocationIds = $monitoredLocations
            ->filter(fn (Location $location): bool => (bool) $location->outbound)
            ->pluck('id')
            ->all();

        $inboundLocationIds = $monitoredLocations
            ->filter(fn (Location $location): bool => ! $location->outbound && (bool) $location->inbound)
            ->pluck('id')
            ->all();

        $shipmentsByPickupLocation = Shipment::query()
            ->selectRaw('pickup_location_id as location_id, status, COUNT(*) as shipment_count')
            ->whereNotNull('pickup_location_id')
            ->whereRaw('LOWER(status) <> ?', ['delivered'])
            ->when(! empty($outboundLocationIds), fn ($query) => $query->whereIn('pickup_location_id', $outboundLocationIds))
            ->groupBy('location_id', 'status')
            ->orderBy('location_id')
            ->orderBy('status')
            ->get()
            ->groupBy('location_id');

        $shipmentsByDcLocation = Shipment::query()
            ->selectRaw('dc_location_id as location_id, status, COUNT(*) as shipment_count')
            ->whereNotNull('dc_location_id')
            ->whereRaw('LOWER(status) <> ?', ['delivered'])
            ->when(! empty($inboundLocationIds), fn ($query) => $query->whereIn('dc_location_id', $inboundLocationIds))
            ->groupBy('location_id', 'status')
            ->orderBy('location_id')
            ->orderBy('status')
            ->get()
            ->groupBy('location_id');

        $unassignedByPickupLocation = Shipment::query()
            ->selectRaw('pickup_location_id as location_id, COUNT(*) as unassigned_shipment_count')
            ->whereNotNull('pickup_location_id')
            ->whereNull('carrier_id')
            ->whereRaw('LOWER(status) <> ?', ['delivered'])
            ->when(! empty($outboundLocationIds), fn ($query) => $query->whereIn('pickup_location_id', $outboundLocationIds))
            ->groupBy('location_id')
            ->get()
            ->pluck('unassigned_shipment_count', 'location_id');

        $unassignedByDcLocation = Shipment::query()
            ->selectRaw('dc_location_id as location_id, COUNT(*) as unassigned_shipment_count')
            ->whereNotNull('dc_location_id')
            ->whereNull('carrier_id')
            ->whereRaw('LOWER(status) <> ?', ['delivered'])
            ->when(! empty($inboundLocationIds), fn ($query) => $query->whereIn('dc_location_id', $inboundLocationIds))
            ->groupBy('location_id')
            ->get()
            ->pluck('unassigned_shipment_count', 'location_id');

        return $monitoredLocations
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(function (Location $location) use ($shipmentsByPickupLocation, $shipmentsByDcLocation, $allPickupCodes, $allDcCodes, $allStatuses, $unassignedByPickupLocation, $unassignedByDcLocation): array {
                $usesOutboundDimension = (bool) $location->outbound;
                $locationDimension = $usesOutboundDimension ? 'pickup' : 'dc';

                $locationIncludedCodes = collect([$location->short_code])
                    ->filter()
                    ->values();

                $shipmentsByLocation = $usesOutboundDimension
                    ? $shipmentsByPickupLocation
                    : $shipmentsByDcLocation;

                $unassignedByLocation = $usesOutboundDimension
                    ? $unassignedByPickupLocation
                    : $unassignedByDcLocation;

                $allLocationCodes = $usesOutboundDimension
                    ? $allPickupCodes
                    : $allDcCodes;

                $statusBreakdown = collect($shipmentsByLocation->get($location->id, collect()))
                    ->map(function ($shipmentGroup) use ($allLocationCodes, $allStatuses, $locationIncludedCodes, $locationDimension): array {
                        $status = $shipmentGroup->status;

                        return [
                            'status' => $status,
                            'count' => (int) $shipmentGroup->shipment_count,
                            'shipment_index_url' => $this->shipmentIndexUrlByLocationDimension(
                                $locationDimension,
                                $locationIncludedCodes,
                                collect([$status]),
                                $allLocationCodes,
                                $allStatuses,
                            ),
                        ];
                    })
                    ->sortBy('status', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'short_code' => $location->short_code,
                    'monitor_type' => $locationDimension,
                    'shipment_count' => $statusBreakdown->sum('count'),
                    'unassigned_shipment_count' => (int) ($unassignedByLocation[$location->id] ?? 0),
                    'unassigned_shipment_index_url' => $this->shipmentIndexUnassignedUrlByLocationDimension(
                        $locationDimension,
                        $locationIncludedCodes,
                        $allLocationCodes,
                        $allStatuses,
                    ),
                    'shipment_index_url' => $this->shipmentIndexUrlByLocationDimension(
                        $locationDimension,
                        $locationIncludedCodes,
                        $allStatuses->reject(fn (string $status): bool => strtolower($status) === 'delivered')->values(),
                        $allLocationCodes,
                        $allStatuses,
                    ),
                    'status_breakdown' => $statusBreakdown->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, string>  $includedLocationCodes
     * @param  Collection<int, string>  $includedStatuses
     * @param  Collection<int, string>  $allLocationCodes
     * @param  Collection<int, string>  $allStatuses
     */
    private function shipmentIndexUrlByLocationDimension(
        string $locationDimension,
        Collection $includedLocationCodes,
        Collection $includedStatuses,
        Collection $allLocationCodes,
        Collection $allStatuses,
    ): string {
        $locationFilterKey = $locationDimension === 'dc'
            ? 'excluded_dc_locations'
            : 'excluded_pickup_locations';

        return route('admin.shipments.index', [
            $locationFilterKey => $allLocationCodes
                ->reject(fn (string $shortCode): bool => $includedLocationCodes->contains($shortCode))
                ->values()
                ->all(),
            'excluded_statuses' => $allStatuses
                ->reject(fn (string $status): bool => $includedStatuses->contains($status))
                ->values()
                ->all(),
        ], false);
    }

    /**
     * @param  Collection<int, string>  $includedLocationCodes
     * @param  Collection<int, string>  $allLocationCodes
     * @param  Collection<int, string>  $allStatuses
     */
    private function shipmentIndexUnassignedUrlByLocationDimension(
        string $locationDimension,
        Collection $includedLocationCodes,
        Collection $allLocationCodes,
        Collection $allStatuses,
    ): string {
        $locationFilterKey = $locationDimension === 'dc'
            ? 'excluded_dc_locations'
            : 'excluded_pickup_locations';

        return route('admin.shipments.index', [
            $locationFilterKey => $allLocationCodes
                ->reject(fn (string $shortCode): bool => $includedLocationCodes->contains($shortCode))
                ->values()
                ->all(),
            'excluded_statuses' => $allStatuses
                ->filter(fn (string $status): bool => strtolower($status) === 'delivered')
                ->values()
                ->all(),
            'only_unassigned' => 1,
        ], false);
    }

    /**
     * @return array<int, array{id:int, name:string, short_code:?string, active_shipment_count:int, shipment_index_url:string, status_breakdown:array<int, array{status:string, count:int, shipment_index_url:string}>}>
     */
    private function carrierActiveShipmentSummary(): array
    {
        $allStatuses = Shipment::query()
            ->distinct('status')
            ->pluck('status')
            ->filter()
            ->sort()
            ->values();

        $allCarrierNames = Carrier::query()
            ->pluck('name')
            ->filter()
            ->sort()
            ->values();

        $shipmentsByCarrier = Shipment::query()
            ->selectRaw('carrier_id, status, COUNT(*) as shipment_count')
            ->whereNotNull('carrier_id')
            ->whereRaw('LOWER(status) NOT IN (?, ?)', ['delivered', 'cancelled'])
            ->groupBy('carrier_id', 'status')
            ->orderBy('carrier_id')
            ->orderBy('status')
            ->get()
            ->groupBy('carrier_id');

        $carrierIds = $shipmentsByCarrier->keys();

        return Carrier::query()
            ->whereIn('id', $carrierIds)
            ->orderBy('name')
            ->get(['id', 'name', 'short_code'])
            ->map(function (Carrier $carrier) use ($shipmentsByCarrier, $allCarrierNames, $allStatuses): array {
                $statusBreakdown = collect($shipmentsByCarrier->get($carrier->id, collect()))
                    ->map(fn ($group): array => [
                        'status' => $group->status,
                        'count' => (int) $group->shipment_count,
                        'shipment_index_url' => $this->carrierShipmentIndexUrl(
                            $carrier->name,
                            $allCarrierNames,
                            $allStatuses,
                            $group->status,
                        ),
                    ])
                    ->sortBy('status', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                return [
                    'id' => $carrier->id,
                    'name' => $carrier->name,
                    'short_code' => $carrier->short_code,
                    'active_shipment_count' => $statusBreakdown->sum('count'),
                    'shipment_index_url' => $this->carrierShipmentIndexUrl(
                        $carrier->name,
                        $allCarrierNames,
                        $allStatuses,
                    ),
                    'status_breakdown' => $statusBreakdown->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $allCarrierNames
     * @param  \Illuminate\Support\Collection<int, string>  $allStatuses
     */
    private function carrierShipmentIndexUrl(
        string $carrierName,
        $allCarrierNames,
        $allStatuses,
        ?string $singleStatus = null,
    ): string {
        $excludedCarriers = $allCarrierNames
            ->reject(fn (string $name): bool => $name === $carrierName)
            ->values()
            ->all();

        if ($singleStatus !== null) {
            $excludedStatuses = $allStatuses
                ->reject(fn (string $status): bool => $status === $singleStatus)
                ->values()
                ->all();
        } else {
            $excludedStatuses = $allStatuses
                ->filter(fn (string $status): bool => in_array(strtolower($status), ['delivered', 'cancelled'], true))
                ->values()
                ->all();
        }

        return route('admin.shipments.index', [
            'excluded_carriers' => $excludedCarriers,
            'excluded_statuses' => $excludedStatuses,
        ], false);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $includedPickupCodes
     * @param  \Illuminate\Support\Collection<int, string>  $includedStatuses
     * @param  \Illuminate\Support\Collection<int, string>  $allPickupCodes
     * @param  \Illuminate\Support\Collection<int, string>  $allStatuses
     */
    private function shipmentIndexUrl($includedPickupCodes, $includedStatuses, $allPickupCodes, $allStatuses): string
    {
        return route('admin.shipments.index', [
            'excluded_pickup_locations' => $allPickupCodes
                ->reject(fn (string $shortCode): bool => $includedPickupCodes->contains($shortCode))
                ->values()
                ->all(),
            'excluded_statuses' => $allStatuses
                ->reject(fn (string $status): bool => $includedStatuses->contains($status))
                ->values()
                ->all(),
        ], false);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $includedPickupCodes
     * @param  \Illuminate\Support\Collection<int, string>  $allPickupCodes
     * @param  \Illuminate\Support\Collection<int, string>  $allStatuses
     */
    private function shipmentIndexUnassignedUrl($includedPickupCodes, $allPickupCodes, $allStatuses): string
    {
        return route('admin.shipments.index', [
            'excluded_pickup_locations' => $allPickupCodes
                ->reject(fn (string $shortCode): bool => $includedPickupCodes->contains($shortCode))
                ->values()
                ->all(),
            'excluded_statuses' => $allStatuses
                ->filter(fn (string $status): bool => strtolower($status) === 'delivered')
                ->values()
                ->all(),
            'only_unassigned' => 1,
        ], false);
    }

    /**
     * @return array<int, array{id:int, name:string, offered_shipments_count:int, assigned_shipments_count:int}>
     */
    private function offerActivitySummary(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return User::query()
            ->select('users.id', 'users.name')
            ->join('carrier_shipment_offers as offers', 'offers.offered_by_user_id', '=', 'users.id')
            ->join('shipments', 'shipments.id', '=', 'offers.shipment_id')
            ->whereNull('shipments.deleted_at')
            ->whereBetween('offers.created_at', [$start, $end])
            ->groupBy('users.id', 'users.name')
            ->selectRaw('COUNT(DISTINCT offers.shipment_id) as offered_shipments_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN shipments.carrier_id IS NOT NULL THEN offers.shipment_id END) as assigned_shipments_count')
            ->orderByDesc('offered_shipments_count')
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $offerUser): array => [
                'id' => $offerUser->id,
                'name' => $offerUser->name,
                'offered_shipments_count' => (int) $offerUser->offered_shipments_count,
                'assigned_shipments_count' => (int) $offerUser->assigned_shipments_count,
            ])
            ->all();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function lastCalendarWeekRange(): array
    {
        $lastWeekStart = CarbonImmutable::now()
            ->subWeek()
            ->startOfWeek(CarbonInterface::SUNDAY);

        return [$lastWeekStart, $lastWeekStart->endOfWeek(CarbonInterface::SATURDAY)];
    }

    private function dashboardPreferencesFor(User $user): array
    {
        $rawPreferences = is_array($user->dashboard_preferences ?? null)
            ? $user->dashboard_preferences
            : [];

        $defaultSections = collect(self::DASHBOARD_SECTION_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => true])
            ->all();

        $sections = collect($defaultSections)
            ->merge($rawPreferences['sections'] ?? [])
            ->only(self::DASHBOARD_SECTION_KEYS)
            ->map(fn ($value): bool => (bool) $value)
            ->all();

        $defaultMonitoredLocationIds = Location::query()
            ->where('type', 'pickup')
            ->pluck('guid')
            ->filter()
            ->values()
            ->all();

        $monitoredLocationIds = collect($rawPreferences['monitored_location_ids'] ?? $defaultMonitoredLocationIds)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'sections' => $sections,
            'monitored_location_ids' => $monitoredLocationIds,
        ];
    }

    /**
     * @return array<int, array{id:string, name:string, short_code:?string, type:string, inbound:bool, outbound:bool}>
     */
    private function availableMonitoredLocations(): array
    {
        return Location::query()
            ->orderBy('short_code')
            ->orderBy('name')
            ->get(['guid', 'name', 'short_code', 'type', 'inbound', 'outbound'])
            ->map(fn (Location $location): array => [
                'id' => $location->guid,
                'name' => $location->name,
                'short_code' => $location->short_code,
                'type' => $location->type,
                'inbound' => (bool) $location->inbound,
                'outbound' => (bool) $location->outbound,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, string>  $monitoredLocationGuids
     * @return Collection<int, Location>
     */
    private function resolveMonitoredLocations(Collection $monitoredLocationGuids): Collection
    {
        if ($monitoredLocationGuids->isEmpty()) {
            return collect();
        }

        $locationsByGuid = Location::query()
            ->whereIn('guid', $monitoredLocationGuids->all())
            ->get(['id', 'guid', 'name', 'short_code', 'type', 'inbound', 'outbound'])
            ->keyBy('guid');

        return $monitoredLocationGuids
            ->map(fn (string $guid) => $locationsByGuid->get($guid))
            ->filter(fn ($location): bool => $location instanceof Location)
            ->values();
    }
}
