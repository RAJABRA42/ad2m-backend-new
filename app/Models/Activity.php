<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'mission_id', 'performed_by_id', 'action_type',
        'description', 'old_data', 'new_data'
    ];

    protected $casts = [
        // Crucial pour les données de traçabilité
        'old_data' => 'array', 
        'new_data' => 'array', 
    ];

    /**
     * L'activité est liée à une mission.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * L'activité a été réalisée par cet utilisateur.
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }
}
