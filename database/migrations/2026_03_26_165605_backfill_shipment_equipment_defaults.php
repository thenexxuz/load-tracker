<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_STRAP_QTY_BY_RACK_QTY = [
        1 => 3,
        2 => 5,
        3 => 8,
        4 => 10,
        5 => 13,
        6 => 15,
        7 => 18,
        8 => 20,
        9 => 23,
        10 => 25,
        11 => 28,
        12 => 33,
        13 => 36,
        14 => 38,
        15 => 41,
        16 => 43,
        17 => 43,
        18 => 45,
        19 => 48,
        20 => 50,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        DB::table('shipments')
            ->select('id', 'rack_qty')
            ->orderBy('id')
            ->chunkById(200, function ($shipments): void {
                foreach ($shipments as $shipment) {
                    $equipmentDefaults = $this->defaultEquipmentCountsForRackQty((int) $shipment->rack_qty);

                    DB::table('shipments')
                        ->where('id', $shipment->id)
                        ->update([
                            'load_bar_qty' => $equipmentDefaults['load_bar_qty'],
                            'strap_qty' => $equipmentDefaults['strap_qty'],
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}

    /**
     * @return array{load_bar_qty:int, strap_qty:int}
     */
    private function defaultEquipmentCountsForRackQty(int $rackQty): array
    {
        $normalizedRackQty = max(0, min($rackQty, max(array_keys(self::DEFAULT_STRAP_QTY_BY_RACK_QTY))));

        if ($normalizedRackQty === 0) {
            return [
                'load_bar_qty' => 0,
                'strap_qty' => 0,
            ];
        }

        return [
            'load_bar_qty' => 2,
            'strap_qty' => self::DEFAULT_STRAP_QTY_BY_RACK_QTY[$normalizedRackQty],
        ];
    }
};
