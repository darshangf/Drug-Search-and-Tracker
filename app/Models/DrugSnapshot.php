<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Drug Snapshot Model
 * 
 * Stores cached drug information from RxNorm API
 * Updated on-demand when data becomes stale
 */
class DrugSnapshot extends Model
{
    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'rxcui';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'rxcui',
        'drug_name',
        'ingredient_base_names',
        'dosage_forms',
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ingredient_base_names' => 'array',
        'dosage_forms' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the user medications for this drug.
     */
    public function userMedications(): HasMany
    {
        return $this->hasMany(UserMedication::class, 'rxcui', 'rxcui');
    }

    /**
     * Check if the snapshot is stale and needs refreshing
     *
     * @param int $days
     * @return bool
     */
    public function isStale(int $days = 30): bool
    {
        return $this->last_synced_at->addDays($days)->isPast();
    }
}
