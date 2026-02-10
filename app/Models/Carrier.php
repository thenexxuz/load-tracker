<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Carrier extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'guid',
        'short_code',
        'wt_code',
        'name',
        'emails',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'guid' => 'string',
        'emails' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($carrier) {
            if (empty($carrier->guid)) {
                $carrier->guid = (string) Str::uuid();
            }
        });
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ────────────────────────────────────────────────────────────────────────────────

    /**
     * Get all shipments where this carrier is assigned.
     */
    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // HELPER METHODS FOR RELATED SHIPMENTS
    // ────────────────────────────────────────────────────────────────────────────────

    /**
     * Get the total number of shipments associated with this carrier.
     */
    public function getShipmentCountAttribute(): int
    {
        return $this->shipments()->count();
    }

    /**
     * Get a formatted string showing shipment count with status breakdown.
     * Example: "12 shipments (5 Pending, 4 In Transit, 3 Delivered)"
     */
    public function getShipmentSummaryAttribute(): string
    {
        $counts = $this->shipments()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($counts);

        if ($total === 0) {
            return 'No shipments';
        }

        $summary = [];
        foreach (['Pending', 'In Transit', 'Delivered', 'Cancelled'] as $status) {
            if (isset($counts[$status])) {
                $summary[] = "{$counts[$status]} $status";
            }
        }

        return "$total shipment".($total === 1 ? '' : 's')
            .($summary ? ' ('.implode(', ', $summary).')' : '');
    }

    /**
     * Get the most recent shipment (if any).
     */
    public function getLatestShipmentAttribute(): ?Shipment
    {
        return $this->shipments()
            ->latest('created_at')
            ->first();
    }

    /**
     * Get a short string about the most recent shipment (useful for tooltips or lists).
     * Example: "Latest: SHIP-250122-ABCDEF (Delivered on 2025-12-15)"
     */
    public function getLatestShipmentInfoAttribute(): string
    {
        $latest = $this->latestShipment;

        if (! $latest) {
            return 'No shipments yet';
        }

        $date = $latest->delivery_date
            ? $latest->delivery_date->format('M j, Y')
            : ($latest->drop_date ? $latest->drop_date->format('M j, Y') : 'No date');

        return "Latest: {$latest->shipment_number} ({$latest->status}) on {$date}";
    }

    // Optional: Accessor for emails as array
    public function getEmailListAttribute(): array
    {
        if (! $this->emails) {
            return [];
        }

        return array_map('trim', explode(',', $this->emails));
    }

    // Scope for active carriers
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected static $logAttributes = ['*'];

    protected static $logOnlyDirty = true;

    protected static $submitEmptyLogs = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'short_code',
                'wt_code',
                'name',
                'emails',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "This carrier has been {$eventName}";
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
