<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'type',  // 'flat' or 'per_mile'
        'pickup_location_id',
        'dc_location_id',
        'carrier_id',
        'rate',
        'effective_from',
        'effective_to',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => 'string',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the pickup location this rate applies to.
     */
    public function pickupLocation()
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    /**
     * Get the destination (DC) location this rate applies to.
     */
    public function dcLocation()
    {
        return $this->belongsTo(Location::class, 'dc_location_id');
    }

    /**
     * Get the carrier offering this rate.
     */
    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * Scope a query to only include active rates (based on effective dates).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('effective_from')
                ->orWhere('effective_from', '<=', now());
        })->where(function ($q) {
            $q->whereNull('effective_to')
                ->orWhere('effective_to', '>=', now());
        });
    }

    /**
     * Determine if this is a per-mile rate.
     */
    public function isPerMile(): bool
    {
        return $this->type === 'per_mile';
    }

    /**
     * Determine if this is a flat rate.
     */
    public function isFlat(): bool
    {
        return $this->type === 'flat';
    }

    /**
     * Calculate the total rate for a given mileage.
     *
     * @param float|null $miles
     * @return float|null
     */
    public function calculateTotal(?float $miles = null): ?float
    {
        if ($this->isFlat()) {
            return $this->rate; // treating as flat amount
        }

        if ($this->isPerMile() && $miles !== null) {
            return round($miles * $this->rate, 2);
        }

        return null;
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }
}