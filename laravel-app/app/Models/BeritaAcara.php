<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BeritaAcara extends Model
{
    use HasFactory;

    protected $table = 'berita_acara';

    protected $fillable = [
        'title',
        'category',
        'status',
        'date_text',
        'time_text',
        'location',
        'organizer',
        'attendee_count',
        'summary',
        'content',
        'banner_img',
        'attachment_file_name',
        'attachment_file_url',
        'external_link',
        'author',
    ];

    protected function casts(): array
    {
        return [
            'attendee_count' => 'integer',
        ];
    }
}
