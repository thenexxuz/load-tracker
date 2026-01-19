<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'guid', 'short_code', 'name', 'address', 'city', 'state', 'zip', 'country',
        'type', 'recycling_location_id', 'latitude', 'longitude', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'guid' => 'string',
    ];

    protected static function booted()
    {
        static::creating(function ($location) {
            if (empty($location->guid)) {
                $location->guid = (string) Str::uuid();
            }
        });

        // Enforce rule: only distribution centers can have a recycling location
        static::saving(function ($location) {
            if ($location->recycling_location_id !== null && $location->type !== 'distribution_center') {
                throw new \Exception('Only distribution centers can have a recycling location.');
            }
        });
    }

    // The ONE recycling location this DC uses (if type = distribution_center)
    public function recyclingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'recycling_location_id');
    }

    // All DCs that use THIS location as their recycling location
    public function distributionCenters(): HasMany
    {
        return $this->hasMany(Location::class, 'recycling_location_id')
            ->where('type', 'distribution_center');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

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
}
