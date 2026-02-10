<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationDistance extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'location_distances';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'from_location_id',
        'to_location_id',
        'distance_km',
        'distance_miles',
        'duration_text',
        'duration_minutes',
        'route_coords',
        'calculated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'distance_km' => 'float',
        'distance_miles' => 'float',
        'duration_minutes' => 'integer',
        'route_coords' => 'array',
        'calculated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays/JSON.
     *
     * @var array<string>
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Get the originating (from) location.
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * Get the destination (to) location.
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * Get a display-friendly distance string.
     */
    public function getDistanceDisplayAttribute(): string
    {
        if (is_null($this->distance_km)) {
            return '—';
        }

        return round($this->distance_km, 1).' km ('.round($this->distance_miles, 1).' mi)';
    }

    /**
     * Get a display-friendly duration string.
     */
    public function getDurationDisplayAttribute(): string
    {
        return $this->duration_text ?? '—';
    }

    /**
     * Determine if this distance record is outdated.
     *
     * @param  int  $days  Days after which a record is considered stale
     */
    public function isOutdated(int $days = 30): bool
    {
        return $this->calculated_at && $this->calculated_at->lt(now()->subDays($days));
    }

    /**
     * Scope: Only include records that are not outdated.
     */
    public function scopeFresh($query, int $days = 30)
    {
        return $query->where('calculated_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Filter by a specific from/to location pair.
     */
    public function scopeBetween($query, Location $from, Location $to)
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->where('from_location_id', $from->id)
                ->where('to_location_id', $to->id);
        })->orWhere(function ($q) use ($from, $to) {
            $q->where('from_location_id', $to->id)
                ->where('to_location_id', $from->id);
        });
    }
}
