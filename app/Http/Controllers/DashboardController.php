<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $isAdminOrSupervisor = $user->hasAnyRole(['administrator', 'supervisor']);

        $data = [
            'isAdminOrSupervisor' => $isAdminOrSupervisor,
        ];

        if ($isAdminOrSupervisor) {
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

            [$lastWeekStart, $lastWeekEnd] = $this->lastCalendarWeekRange();

            $data['pickupLocationShipmentSummary'] = $this->pickupLocationShipmentSummary();
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

    /**
     * @return array<int, array{id:int, name:string, short_code:?string, shipment_count:int, unassigned_shipment_count:int, status_breakdown:array<int, array{status:string, count:int}>}>
     */
    private function pickupLocationShipmentSummary(): array
    {
        $allPickupCodes = Location::query()
            ->where('type', 'pickup')
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

        $locations = Location::query()
            ->where('type', 'pickup')
            ->orderBy('name')
            ->get(['id', 'name', 'short_code']);

        $shipmentsByLocation = Shipment::query()
            ->selectRaw('pickup_location_id, status, COUNT(*) as shipment_count')
            ->whereNotNull('pickup_location_id')
            ->whereRaw('LOWER(status) <> ?', ['delivered'])
            ->groupBy('pickup_location_id', 'status')
            ->orderBy('pickup_location_id')
            ->orderBy('status')
            ->get()
            ->groupBy('pickup_location_id');

        $unassignedByLocation = Shipment::query()
            ->selectRaw('pickup_location_id, COUNT(*) as unassigned_shipment_count')
            ->whereNotNull('pickup_location_id')
            ->whereNull('carrier_id')
            ->whereRaw('LOWER(status) <> ?', ['delivered'])
            ->groupBy('pickup_location_id')
            ->get()
            ->pluck('unassigned_shipment_count', 'pickup_location_id');

        return $locations
            ->map(function (Location $location) use ($shipmentsByLocation, $allPickupCodes, $allStatuses, $unassignedByLocation): array {
                $locationIncludedCodes = collect([$location->short_code])
                    ->filter()
                    ->values();

                $statusBreakdown = collect($shipmentsByLocation->get($location->id, collect()))
                    ->map(function ($shipmentGroup) use ($allPickupCodes, $allStatuses, $locationIncludedCodes): array {
                        $status = $shipmentGroup->status;

                        return [
                            'status' => $status,
                            'count' => (int) $shipmentGroup->shipment_count,
                            'shipment_index_url' => $this->shipmentIndexUrl(
                                $locationIncludedCodes,
                                collect([$status]),
                                $allPickupCodes,
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
                    'shipment_count' => $statusBreakdown->sum('count'),
                    'unassigned_shipment_count' => (int) ($unassignedByLocation[$location->id] ?? 0),
                    'unassigned_shipment_index_url' => $this->shipmentIndexUnassignedUrl(
                        $locationIncludedCodes,
                        $allPickupCodes,
                        $allStatuses,
                    ),
                    'shipment_index_url' => $this->shipmentIndexUrl(
                        $locationIncludedCodes,
                        $allStatuses->reject(fn (string $status): bool => strtolower($status) === 'delivered')->values(),
                        $allPickupCodes,
                        $allStatuses,
                    ),
                    'status_breakdown' => $statusBreakdown->all(),
                ];
            })
            ->all();
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
}
