<?php

namespace App\Http\Controllers;

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
     * @return array<int, array{id:int, name:string, short_code:?string, shipment_count:int, status_breakdown:array<int, array{status:string, count:int}>}>
     */
    private function pickupLocationShipmentSummary(): array
    {
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

        return $locations
            ->map(function (Location $location) use ($shipmentsByLocation): array {
                $statusBreakdown = collect($shipmentsByLocation->get($location->id, collect()))
                    ->map(fn ($shipmentGroup): array => [
                        'status' => $shipmentGroup->status,
                        'count' => (int) $shipmentGroup->shipment_count,
                    ])
                    ->sortBy('status', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'short_code' => $location->short_code,
                    'shipment_count' => $statusBreakdown->sum('count'),
                    'status_breakdown' => $statusBreakdown->all(),
                ];
            })
            ->all();
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
