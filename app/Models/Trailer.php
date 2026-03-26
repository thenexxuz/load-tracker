<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trailer extends Model
{
    protected $fillable = [
        'guid',
        'number',
        'carrier_id',
        'current_location_id',
        'type',
        'capacity',
        'license_plate',
        'status',
        'purchased_date',
        'is_active',
    ];

    protected $casts = [
        'purchased_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * The carrier that owns this trailer.
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    /**
     * Shipments using this trailer.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Shipments where this trailer is loaned from another carrier.
     */
    public function loanedShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'loaned_from_trailer_id');
    }
}
