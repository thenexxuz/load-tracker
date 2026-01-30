<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Shipment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'guid',
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
        'drayage',
        'on_site',
        'shipped',
        'recycling_sent',
        'paperwork_sent',
        'delivery_alert_sent',
    ];

    protected $casts = [
        'drop_date' => 'date',
        'pickup_date' => 'datetime',
        'delivery_date' => 'datetime',
        'on_site' => 'boolean',
        'shipped' => 'boolean',
        'recycling_sent' => 'boolean',
        'paperwork_sent' => 'boolean',
        'delivery_alert_sent' => 'boolean',
        'drayage' => 'boolean',
    ];

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
                'drayage',
                'on_site',
                'shipped',
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
}
