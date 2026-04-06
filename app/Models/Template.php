<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Template extends Model
{
    use HasFactory;

    public const TOKEN_PATTERN = '/\{\{\s*([^\}\s]+)\s*\}\}/';

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

    public function scheduledItems(): HasMany
    {
        return $this->hasMany(ScheduledItem::class);
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /**
     * @return array<string>
     */
    public static function extractTokenNames(string $content): array
    {
        preg_match_all(self::TOKEN_PATTERN, $content, $matches);

        if (! isset($matches[1]) || ! is_array($matches[1])) {
            return [];
        }

        $tokens = array_map(static fn (string $name): string => strtolower(trim($name)), $matches[1]);

        return array_values(array_unique(array_filter($tokens, static fn (string $name): bool => $name !== '')));
    }

    /**
     * @param  array<string, mixed>  $baseReplacements
     * @param  array<string, string>|null  $tokenMessages
     * @return array<string, string>
     */
    public static function resolveTemplateTokenReplacements(array $baseReplacements = [], ?array $tokenMessages = null): array
    {
        $tokens = $tokenMessages ?? self::query()
            ->where('model_type', self::class)
            ->whereNull('model_id')
            ->get(['name', 'message'])
            ->mapWithKeys(function (self $token): array {
                $key = strtolower(trim((string) $token->name));

                if ($key === '') {
                    return [];
                }

                return [$key => (string) ($token->message ?? '')];
            })
            ->all();

        $resolved = [];
        $resolving = [];

        $resolveToken = function (string $tokenName) use (&$resolveToken, &$resolved, &$resolving, $tokens, $baseReplacements): string {
            if (array_key_exists($tokenName, $resolved)) {
                return $resolved[$tokenName];
            }

            if (array_key_exists($tokenName, $resolving)) {
                throw new \RuntimeException($tokenName);
            }

            $resolving[$tokenName] = true;
            $rawValue = $tokens[$tokenName] ?? '';

            $resolvedValue = preg_replace_callback(self::TOKEN_PATTERN, function (array $matches) use (&$resolveToken, $tokens, $baseReplacements): string {
                $key = strtolower(trim($matches[1]));

                if (array_key_exists($key, $baseReplacements)) {
                    return (string) $baseReplacements[$key];
                }

                if (! array_key_exists($key, $tokens)) {
                    return $matches[0];
                }

                return $resolveToken($key);
            }, $rawValue) ?? $rawValue;

            unset($resolving[$tokenName]);
            $resolved[$tokenName] = $resolvedValue;

            return $resolvedValue;
        };

        foreach (array_keys($tokens) as $tokenName) {
            $resolveToken($tokenName);
        }

        return $resolved;
    }
}
