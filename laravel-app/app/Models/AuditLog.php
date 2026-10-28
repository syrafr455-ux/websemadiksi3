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
            // Kolom description varchar(255); PostgreSQL menolak teks lebih panjang (nama + alasan pengguna sering melampauinya).
            'description' => $description === null ? null : mb_substr($description, 0, 255),
            'metadata' => $metadata,
            'ip_address' => request()?->ip(),
        ]);
    }
}
