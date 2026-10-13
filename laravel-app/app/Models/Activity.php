<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'type',
        'date_text',
        'event_date',
        'description',
        'status',
        'price',
        'tags',
        'image_url',
        'is_latest',
        'is_closed',
        'location',
        'rundown',
        'speaker',
        'require_file_upload',
        'file_upload_instruction',
        'enable_seat_booking',
        'seat_layout_config',
        'quota',
        'xp_points',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'tags' => 'array',
            'seat_layout_config' => 'array',
            'is_latest' => 'boolean',
            'is_closed' => 'boolean',
            'require_file_upload' => 'boolean',
            'enable_seat_booking' => 'boolean',
            'quota' => 'integer',
            'xp_points' => 'integer',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ActivityRegistration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
