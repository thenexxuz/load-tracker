<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Shipment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

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

    protected $fillable = [
        'guid',
        'shipment_number',
        'bol',
        'po_number',
        'status',
        'pickup_location_id',
        'dc_location_id',
        'carrier_id',
        'trailer_id',
        'loaned_from_trailer_id',
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
    ];

    protected $casts = [
        'drop_date' => 'date',
        'pickup_date' => 'datetime',
        'delivery_date' => 'datetime',
        'on_site' => 'datetime',
        'shipped' => 'datetime',
        'crossed' => 'datetime',
        'recycling_sent' => 'datetime',
        'paperwork_sent' => 'datetime',
        'delivery_alert_sent' => 'datetime',
    ];

    /**
     * @return array{load_bar_qty:int, strap_qty:int}
     */
    public static function defaultEquipmentCountsForRackQty(int $rackQty): array
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

    public static function normalizeStatusLabel(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalizedStatus = trim($status);

        if ($normalizedStatus === '') {
            return $normalizedStatus;
        }

        $normalizedComparableStatus = Str::of($normalizedStatus)
            ->lower()
            ->replace('-', ' ')
            ->squish()
            ->value();

        if ($normalizedComparableStatus === 'checked in') {
            return 'Checked In';
        }

        return $normalizedStatus;
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = self::normalizeStatusLabel($value);
    }

    protected static function booted()
    {
        static::creating(function ($shipment) {
            if (empty($shipment->guid)) {
                $shipment->guid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function dcLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'dc_location_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class);
    }

    public function loanedFromTrailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class, 'loaned_from_trailer_id');
    }

    public function offeredCarriers(): BelongsToMany
    {
        return $this->belongsToMany(Carrier::class, 'carrier_shipment_offers')
            ->withPivot('offered_by_user_id')
            ->withTimestamps();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    protected static $logAttributes = ['*'];

    protected static $logOnlyDirty = true;

    protected static $submitEmptyLogs = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
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
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "This location has been {$eventName}";
    }

    public function ppw()
    {
        return 'Format table for paperwork generation';
    }

    public function getOnSiteFormattedAttribute()
    {
        return $this->on_site ? $this->on_site->format('m/d/Y H:i') : null;
    }

    public function getShippedFormattedAttribute()
    {
        return $this->shipped ? $this->shipped->format('m/d/Y H:i') : null;
    }

    public function isConsolidation()
    {
        return $this?->consolidation_number ?? false;
    }

    public function consolidationShipments()
    {
        return $this->hasMany(Shipment::class, 'consolidation_number', 'consolidation_number')
            ->where('id', '!=', $this->id);
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function calculateBol()
    {
        try {
            $tracking = (string) $this->shipment_number;
            if (empty($tracking)) {
                \Log::warning("No tracking number for model {$this->id}");

                return null;
            }

            $payload = [
                'trackMode' => 'Domestic',
                'trackBy' => 'reference',
                'trackingNo' => $tracking,
            ];

            \Log::debug('Pegasus request details', [
                'url' => 'https://partners.pegasuslogistics.com/api/getbasictrackingdetails',
                'payload' => $payload,
                'as_json' => json_encode($payload),
            ]);

            $response = \Http::withHeaders(
                [
                    'Referer' => 'https://partners.pegasuslogistics.com/',
                    'accept' => 'application/json',
                ])
                ->post(
                    'https://partners.pegasuslogistics.com/api/getbasictrackingdetails',
                    $payload
                );

            \Log::debug('Pegasus response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && count($data) > 0) {
                    \Log::info('BOL fetched successfully: '.$data[0]['housebill']);
                    $this->bol = $data[0]['housebill'] ?? null;
                    $this->save();

                    return $data[0]['housebill'] ?? null;
                }
                \Log::warning('BOL response is empty or not an array: '.$response->body());

                return null;
            } else {
                \Log::error('Failed to fetch BOL: '.$response->body());

                return null;
            }
        } catch (\Exception $e) {
            \Log::error('Exception in calculateBol: '.$e->getMessage());

            return null;
        }
    }
}
