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
        'dc_id',
        'recycling_id',
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
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Get the distribution center location.
     */
    public function dc(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'dc_id');
    }

    /**
     * Get the recycling location.
     */
    public function recycling(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'recycling_id');
    }

    /**
     * Scope a query to only include recently calculated distances.
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('calculated_at', '>=', now()->subDays($days));
    }

    /**
     * Accessor for formatted distance string.
     */
    public function getDistanceDisplayAttribute(): string
    {
        if ($this->distance_km === null) {
            return '—';
        }

        return $this->distance_km . ' km (' . $this->distance_miles . ' mi)';
    }

    /**
     * Accessor for formatted duration.
     */
    public function getDurationDisplayAttribute(): string
    {
        return $this->duration_text ?? '—';
    }

    /**
     * Check if the distance is outdated (older than 30 days, for example).
     */
    public function isOutdated(): bool
    {
        return $this->calculated_at && $this->calculated_at->lt(now()->subDays(30));
    }
}