<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory;

    /** Fixed set of categories the AI (and the manual picker) chooses from. */
    public const CATEGORIES = [
        'Food & Drink',
        'Groceries',
        'Rent',
        'Utilities',
        'Transport',
        'Travel',
        'Entertainment',
        'Shopping',
        'Other',
    ];

    protected $fillable = [
        'group_id',
        'paid_by',
        'description',
        'amount',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ExpenseShare::class);
    }
}
