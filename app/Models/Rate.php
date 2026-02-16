<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory;

    protected $fillable = [
        'carrier_id',
        'pickup_location_id',
        'dc_location_id',
        'rate',
    ];

    // Relationships
    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function pickupLocation()
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function dcLocation()
    {
        return $this->belongsTo(Location::class, 'dc_location_id');
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
