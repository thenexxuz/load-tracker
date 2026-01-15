<?php

namespace App\Models;

use App\Observers\LocationObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'guid',
        'short_code',
        'name',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'type',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'guid' => 'string',
        'type' => 'string',
    ];

    protected static function booted()
    {
        static::creating(function ($location) {
            if (empty($location->guid)) {
                $location->guid = (string) Str::uuid();
            }
        });
        static::observe(LocationObserver::class);
    }

    // Optional: Scope for active locations
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isPickup(): bool
    {
        return $this->type === 'pickup';
    }

    public function isDistributionCenter(): bool
    {
        return $this->type === 'distribution_center';
    }

    public function isRecycling(): bool
    {
        return $this->type === 'recycling';
    }

    // Optional: Formatted full address
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state ? $this->state.' '.$this->zip : null,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    // A distribution center can have one recycling location
    public function recyclingLocation()
    {
        return $this->morphOne(Location::class, 'recycling_location');
    }

    // Inverse: which distribution center owns this recycling location
    public function distributionCenter()
    {
        return $this->morphTo('recycling_location', 'recycling_location_type', 'recycling_location_id');
    }
}
