<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFormQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'section_id',
        'question_code',
        'title',
        'type',
        'options',
        'required',
        'placeholder',
        'help_text',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'order_index' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CustomFormSection::class, 'section_id');
    }
}
