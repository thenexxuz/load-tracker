<?php

namespace App\Console\Commands;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;
use Illuminate\Console\Command;

class ImportRates extends Command
{
    protected $signature = 'rates:import
                            {file : Path to CSV file}
                            {--country=US : Destination country code}
                            {--dry-run : Parse and validate without writing}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Import lane rates from a CSV with TYPE, NAME, ORIGIN, DESTINATION, and carrier WT code columns.';

    public function handle(): int
    {
        $file = $this->argument('file');
        $country = strtoupper($this->option('country') ?? 'US');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (! $force && ! $dryRun) {
            if (! $this->confirm('This will create/update rates from the file. Continue?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $path = $file;

        if (! file_exists($path) && file_exists(base_path($file))) {
            $path = base_path($file);
        }

        if (! file_exists($path)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Unable to open file: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);
            $this->error('File is empty or header row could not be read.');

            return self::FAILURE;
        }

        $headers = array_map(fn ($col) => strtoupper(trim((string) $col)), $header);

        if (count($headers) < 4 || $headers[0] !== 'TYPE' || $headers[1] !== 'NAME' || $headers[2] !== 'ORIGIN' || $headers[3] !== 'DESTINATION') {
            fclose($handle);
            $this->error('CSV header must be: TYPE, NAME, ORIGIN, DESTINATION, then one or more carrier WT codes.');

            return self::FAILURE;
        }

        $carrierCodes = array_slice($headers, 4);

        if (empty($carrierCodes)) {
            fclose($handle);
            $this->error('No carrier columns found in header.');

            return self::FAILURE;
        }

        $carriers = Carrier::whereIn('wt_code', $carrierCodes)
            ->get()
            ->keyBy(fn (Carrier $carrier) => strtoupper($carrier->wt_code));

        $missing = array_values(array_diff($carrierCodes, $carriers->keys()->all()));

        if (! empty($missing)) {
            $this->warn('Skipping unknown carrier WT codes: '.implode(', ', $missing));
            // Remove missing columns from processing
            $carrierCodes = array_values(array_diff($carrierCodes, $missing));
        }

        if (empty($carrierCodes)) {
            fclose($handle);
            $this->error('No valid carrier columns remain after filtering.');

            return self::FAILURE;
        }

        $rows = 0;
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rows++;

            if (count(array_filter($row, fn ($val) => trim((string) $val) !== '')) === 0) {
                continue;
            }

            $type = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');
            $origin = $this->normalizeOriginShortCode(trim($row[2] ?? ''));
            $destinationCell = trim($row[3] ?? '');

            if ($type === '' || $name === '' || $origin === '' || $destinationCell === '') {
                $this->warn("Skipping row {$rows}: TYPE, NAME, ORIGIN or DESTINATION is empty.");
                $skipped++;

                continue;
            }

            if (! in_array(strtolower($type), ['flat', 'per_mile'], true)) {
                $this->warn("Skipping row {$rows}: TYPE '{$type}' must be 'flat' or 'per_mile'.");
                $skipped++;

                continue;
            }

            $rateType = strtolower($type);

            $pickupLocation = Location::query()
                ->whereRaw('LOWER(short_code) = ?', [strtolower($origin)])
                ->first();

            if (! $pickupLocation) {
                $this->warn("Skipping row {$rows}: pickup location '{$origin}' not found.");
                $skipped++;

                continue;
            }

            $destinationCity = null;
            $destinationState = null;

            if (str_contains($destinationCell, ',')) {
                [$destinationCity, $destinationState] = array_map(fn ($v) => trim($v), explode(',', $destinationCell, 2));
                $destinationState = strtoupper($destinationState);
            } else {
                if (strlen($destinationCell) === 2) {
                    $destinationState = strtoupper($destinationCell);
                } else {
                    $destinationCity = $destinationCell;
                }
            }

            if (! $destinationCity && ! $destinationState) {
                $this->warn("Skipping row {$rows}: DESTINATION '{$destinationCell}' is invalid.");
                $skipped++;

                continue;
            }

            foreach ($carrierCodes as $index => $wtCode) {
                $carrier = $carriers->get(strtoupper($wtCode));

                if (! $carrier) {
                    continue;
                }

                $value = $row[4 + $index] ?? '';
                $value = trim((string) $value);

                if ($value === '') {
                    continue;
                }

                $numericValue = str_replace(['$', ',', ' '], ['', '', ''], $value);

                if (! is_numeric($numericValue)) {
                    $this->warn("Skipping row {$rows} carrier {$wtCode}: invalid numeric rate '{$value}'.");
                    $skipped++;

                    continue;
                }

                $rateAmount = (float) $numericValue;

                if ($rateAmount <= 0) {
                    $this->warn("Skipping row {$rows} carrier {$wtCode}: non-positive rate '{$rateAmount}'.");
                    $skipped++;

                    continue;
                }

                $defaults = [
                    'name' => $name,
                    'type' => $rateType,
                    'pickup_location_id' => $pickupLocation->id,
                    'destination_city' => $destinationCity,
                    'destination_state' => $destinationState,
                    'destination_country' => $country,
                    'carrier_id' => $carrier->id,
                    'rate' => $rateAmount,
                ];

                if (! $dryRun) {
                    $rate = Rate::updateOrCreate(
                        [
                            'carrier_id' => $carrier->id,
                            'pickup_location_id' => $pickupLocation->id,
                            'destination_city' => $destinationCity,
                            'destination_state' => $destinationState,
                            'destination_country' => $country,
                            'type' => $rateType,
                        ],
                        $defaults
                    );

                    if ($rate->wasRecentlyCreated) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } else {
                    $imported++;
                }
            }
        }

        fclose($handle);

        $this->info("Processed {$rows} rows.");

        if ($dryRun) {
            $this->info('Dry run mode: no rates were saved.');
            $this->info("Candidate imported rows: {$imported}");
        } else {
            $this->info("Rates imported: {$imported}");
            $this->info("Rates updated: {$updated}");
        }

        if ($skipped > 0) {
            $this->warn("Skipped entries: {$skipped}");
        }

        return self::SUCCESS;
    }

    private function normalizeOriginShortCode(string $origin): string
    {
        if (strtoupper($origin) === 'ELP-RJS') {
            return 'WIWYNN - RJS';
        }

        return $origin;
    }
}
