<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KipInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'content',
        'date_uploaded',
        'author',
        'attachment_file_name',
        'attachment_url',
        'priority',
    ];
}
