<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'paid_by',
        'description',
        'amount',
        'bill_date',
        'split_among',
        'split_type',
        'custom_split'
    ];

    protected $casts = [
        'split_among' => 'array',
        'custom_split' => 'array',
        'bill_date' => 'date',
        'amount' => 'decimal:2'
    ];

    /**
     * Get the group that owns the bill.
     */
    public function group()
    {
        return $this->belongsTo(SplitGroup::class, 'group_id');
    }

    /**
     * Get the user who paid the bill.
     */
    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Get split details for this bill
     */
    public function getSplitDetails()
    {
        if ($this->split_type === 'custom' && $this->custom_split) {
            return $this->custom_split;
        }

        // Equal split
        $splitCount = count($this->split_among ?? []);
        if ($splitCount === 0) return [];

        $equalAmount = $this->amount / $splitCount;
        $splitDetails = [];

        foreach ($this->split_among as $userId) {
            $splitDetails[$userId] = round($equalAmount, 2);
        }

        return $splitDetails;
    }

    /**
     * Get amount owed by a specific user
     */
    public function getAmountOwedByUser($userId)
    {
        $splitDetails = $this->getSplitDetails();
        return $splitDetails[$userId] ?? 0;
    }

    /**
     * Check if user is involved in this bill
     */
    public function isUserInvolved($userId)
    {
        return in_array($userId, $this->split_among ?? []) || $this->paid_by == $userId;
    }
}
