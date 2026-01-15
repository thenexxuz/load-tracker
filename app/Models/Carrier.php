<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Carrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'guid',
        'short_code',
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
}
