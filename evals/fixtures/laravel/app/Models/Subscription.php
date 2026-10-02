<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['plan', 'seats', 'options', 'ends_at'];

    protected $casts = [
        'options' => 'array',
        'ends_at' => 'datetime',
    ];

    /**
     * Whole days until the subscription ends (0 once it has ended).
     */
    public function daysLeft(): int
    {
        if ($this->ends_at->isPast()) {
            return 0;
        }

        return $this->ends_at->diffInDays(now());
    }
}
