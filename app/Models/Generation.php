<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generation extends Model
{
    protected $fillable = [
        'user_id',
        'provider_id',
        'prompt',
        'size',
        'quality',
        'reference_urls',
        'status',
        'estimated_cost',
        'actual_cost',
        'provider_cost',
        'provider_rates',
        'image_url',
        'provider_usage',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_urls' => 'array',
            'provider_rates' => 'array',
            'provider_usage' => 'array',
            'completed_at' => 'datetime',

            'estimated_cost' => 'decimal:9',
            'actual_cost' => 'decimal:9',
            'provider_cost' => 'decimal:9',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
