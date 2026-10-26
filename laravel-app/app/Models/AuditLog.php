<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(?int $userId, string $action, ?string $description = null, mixed $subject = null, array $metadata = []): self
    {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subject instanceof Model ? get_class($subject) : (is_string($subject) ? $subject : null),
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()?->ip(),
        ]);
    }
}
