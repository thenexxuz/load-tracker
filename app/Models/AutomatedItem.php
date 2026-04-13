<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomatedItem extends Model
{
    /** @use HasFactory<\Database\Factories\AutomatedItemFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'monitorable_type',
        'monitored_fields',
        'role_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monitored_fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, class-string<Model>>
     */
    public static function monitorableMap(): array
    {
        return [
            'shipment' => Shipment::class,
            'location' => Location::class,
            'carrier' => Carrier::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function monitorableLabels(): array
    {
        return [
            'shipment' => 'Shipments',
            'location' => 'Locations',
            'carrier' => 'Carriers',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function monitorableFieldsByKey(): array
    {
        return [
            'shipment' => [
                'shipment_number',
                'bol',
                'po_number',
                'status',
                'pickup_location_id',
                'dc_location_id',
                'carrier_id',
                'drop_date',
                'pickup_date',
                'delivery_date',
                'rack_qty',
                'load_bar_qty',
                'strap_qty',
                'trailer',
                'consolidation_number',
                'drayage',
                'on_site',
                'shipped',
                'crossed',
                'seal_number',
                'drivers_id',
                'recycling_sent',
                'paperwork_sent',
                'delivery_alert_sent',
            ],
            'location' => [
                'short_code',
                'name',
                'type',
                'address',
                'city',
                'state',
                'zip',
                'country',
                'recycling_location_id',
                'latitude',
                'longitude',
                'emails',
                'expected_arrival_time',
                'inbound',
                'outbound',
            ],
            'carrier' => [
                'short_code',
                'wt_code',
                'name',
                'emails',
                'is_active',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function fieldsForClass(string $modelClass): array
    {
        $key = self::keyForClass($modelClass);

        if ($key === null) {
            return [];
        }

        return self::monitorableFieldsByKey()[$key] ?? [];
    }

    public static function keyForClass(string $modelClass): ?string
    {
        $key = array_search($modelClass, self::monitorableMap(), true);

        return is_string($key) ? $key : null;
    }

    public static function classForKey(string $key): ?string
    {
        return self::monitorableMap()[$key] ?? null;
    }

    public static function labelForClass(string $modelClass): string
    {
        $key = self::keyForClass($modelClass);

        if ($key === null) {
            return class_basename($modelClass);
        }

        return self::monitorableLabels()[$key] ?? class_basename($modelClass);
    }
}
