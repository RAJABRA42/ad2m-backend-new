<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AJOUT DES IMPORTATIONS CRITIQUES
use App\Models\Mission; 
use App\Models\User;

class Document extends Model
{
    protected $fillable = [
        'mission_id', 'uploaded_by_id', 'type_document',
        'chemin_fichier', 'nom_original', 'file_mime_type'
    ];

    /**
     * Le document appartient à une mission.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Le document a été téléversé par cet utilisateur.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}