<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scholarship extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'provider',
        'category',
        'status',
        'open_date',
        'close_date',
        'coverage',
        'requirements',
        'selection_stages',
        'description',
        'banner_img',
        'guide_file_name',
        'guide_file_url',
        'apply_url',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
        ];
    }
}
