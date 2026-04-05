<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ScheduledItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'schedule_type',
        'schedule_time',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'template_id',
        'apply_to_all',
        'schedulable_id',
        'schedulable_type',
    ];

    protected function casts(): array
    {
        return [
            'apply_to_all' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }
}
