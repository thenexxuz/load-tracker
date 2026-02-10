<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'model_id',
        'model_type',
        'subject',
        'message',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    // Optional helper: get the related model instance
    public function getRelatedModelAttribute()
    {
        return $this->model;
    }
}
