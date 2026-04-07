<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Notification extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'type',
        'data',
        'read_at',
        'notifiable_type',
        'notifiable_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function isReadByUser(User $user): bool
    {
        return (bool) $this->users()
            ->where('user_id', $user->id)
            ->wherePivot('read_at', '!=', null)
            ->exists();
    }

    public function markAsReadForUser(User $user): void
    {
        $this->users()
            ->where('user_id', $user->id)
            ->updateExistingPivot($user->id, ['read_at' => now()]);
    }

    protected function subject(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->data['subject'] ?? '',
        );
    }

    protected function message(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->data['message'] ?? '',
        );
    }
}
