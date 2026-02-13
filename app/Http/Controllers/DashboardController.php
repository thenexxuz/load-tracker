<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Shipment;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isAdminOrSupervisor = $user->hasAnyRole(['administrator', 'supervisor']);

        $data = [
            'isAdminOrSupervisor' => $isAdminOrSupervisor,
        ];

        if ($isAdminOrSupervisor) {
            // Booked count (simple number)
            $bookedCount = Shipment::where('status', 'Booked')->count();

            // Deliveries per day – last 30 days
            $startDate = now()->subDays(29)->startOfDay();
            $endDate = now()->endOfDay();

            $dailyDeliveries = Shipment::query()
                ->where('status', 'Delivered')
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
        }

        return Inertia::render('Dashboard', $data);
    }
}
