<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFormSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'section_code',
        'title',
        'description',
        'after_section_action',
        'order_index',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CustomFormQuestion::class, 'section_id')->orderBy('order_index');
    }
}
