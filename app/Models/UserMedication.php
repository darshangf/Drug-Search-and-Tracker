<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User Medication Model
 * 
 * Represents a drug in a user's medication list
 */
class UserMedication extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'rxcui',
    ];

    /**
     * Get the user that owns the medication.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the drug snapshot for this medication.
     */
    public function drugSnapshot(): BelongsTo
    {
        return $this->belongsTo(DrugSnapshot::class, 'rxcui', 'rxcui');
    }
}
