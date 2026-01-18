<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avance extends Model
{
    protected $fillable = [
        'mission_id', 'executed_by_id', 'montant', 'type_operation',
        'date_operation', 'numero_piece_paiement'
    ];

    protected $casts = [
        'date_operation' => 'datetime',
        'montant' => 'float', 
    ];

    /**
     * L'avance appartient à une mission.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * L'avance a été exécutée par cet utilisateur (ACCP).
     */
    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_id');
    }
}
