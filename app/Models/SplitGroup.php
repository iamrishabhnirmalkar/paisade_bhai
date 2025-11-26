<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SplitGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    /**
     * Get the user who created this group
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all members of this group
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Check if user is a member of this group (includes creator)
     */
    public function isMember($userId)
    {
        return $this->members()->where('user_id', $userId)->exists() || $this->created_by == $userId;
    }

    /**
     * Get all bills for the group.
     */
    public function bills()
    {
        return $this->hasMany(Bill::class, 'group_id');
    }
}
