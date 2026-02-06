<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Shipment;
use Illuminate\Console\Command;

class PopulateLocationDistances extends Command
{
    protected $signature = 'locations:populate-distances {--force : Recalculate even if already exists}';

    protected $description = 'Populate the LocationDistance table with distances for DC → Recycling and Shipment pickup → DC pairs';

    public function handle()
    {
        $force = $this->option('force');

        $this->info('Starting distance population...');

        // Part 1: DC → Recycling pairs
        $this->info('Processing DC → Recycling distances...');
        $dcs = Location::where('type', 'distribution_center')
            ->whereNotNull('recycling_location_id')
            ->with('recyclingLocation')
            ->get();

        $dcProcessed = 0;
        $dcSkipped = 0;

        foreach ($dcs as $dc) {
            $rec = $dc->recyclingLocation;

            if (!$rec) {
                $this->comment("Skipped DC {$dc->short_code} - no recycling assigned");
                $dcSkipped++;
                continue;
            }

            // Check if already calculated
            if (!$force && $dc->distanceTo($rec)) {
                $dcSkipped++;
                continue;
            }

            $result = $dc->distanceTo($rec, $force);

            if (isset($result['error'])) {
                $this->warn("Failed DC {$dc->short_code} → Recycling {$rec->short_code}: " . $result['error']);
                $dcSkipped++;
                continue;
            }

            $dcProcessed++;
            $this->info("Processed DC {$dc->short_code} → Recycling {$rec->short_code}");
        }

        $this->info("DC → Recycling: Processed {$dcProcessed}, Skipped {$dcSkipped}");

        // Part 2: Shipment pickup → DC pairs
        $this->info('Processing Shipment pickup → DC distances...');
        $shipments = Shipment::with(['pickupLocation', 'dcLocation'])
            ->whereNotNull('pickup_location_id')
            ->whereNotNull('dc_location_id')
            ->get();

        $shipmentProcessed = 0;
        $shipmentSkipped = 0;

        foreach ($shipments as $shipment) {
            $from = $shipment->pickupLocation;
            $to   = $shipment->dcLocation;

            if (!$from || !$to) {
                $this->comment("Skipped shipment {$shipment->id} - missing pickup or DC location");
                $shipmentSkipped++;
                continue;
            }

            // Check if already calculated
            if (!$force && $from->distanceTo($to)) {
                $shipmentSkipped++;
                continue;
            }

            $result = $from->distanceTo($to, $force);

            if (isset($result['error'])) {
                $this->warn("Failed shipment {$shipment->id} (pickup {$from->short_code} → DC {$to->short_code}): " . $result['error']);
                $shipmentSkipped++;
                continue;
            }

            $shipmentProcessed++;
            $this->info("Processed shipment {$shipment->id}: {$from->short_code} → {$to->short_code}");
        }

        $this->info("Shipment pickup → DC: Processed {$shipmentProcessed}, Skipped {$shipmentSkipped}");

        $this->info('All distance population completed successfully.');
    }
}
