<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\User;
use App\Models\Avance;
use App\Models\Document;
use App\Models\Activity;

class Mission extends Model
{
    protected $fillable = [
        // Champs d'acteurs
        'demandeur_id',
        'chef_hierarchique_id',   
        'validation_ch_id',
        'validation_raf_id',
        'validation_cp_id',

        // Informations
        'objet',
        'destination',
        'moyen_deplacement',
        'date_debut',
        'date_fin',
        'motif',

        // Suivi Financier
        'montant_avance_demande',
        'montant_total_justifie',
        'reliquat_a_rembourser',

        // Audit et Statut
        'statut_actuel',
        'date_echeance_audit',
        'date_regularisation',
        'date_cloture',
        'pj_regularise',
        'date_pj_regularise',
        'note_regularisation',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',

        'date_echeance_audit' => 'datetime',
        'date_regularisation' => 'datetime',
        'date_cloture' => 'datetime',

        'pj_regularise' => 'boolean',
        'date_pj_regularise' => 'datetime',
    ];

    // --- RELATIONS ACTEURS / VALIDATEURS (BelongsTo) ---

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    /**
     * Chef hiérarchique assigné à la mission (workflow)
     */
    public function chefHierarchique(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chef_hierarchique_id');
    }

    /**
     * Chef hiérarchique ayant effectivement validé
     */
    public function validateurCH(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_ch_id');
    }

    public function validateurRAF(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_raf_id');
    }

    public function validateurCP(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_cp_id');
    }

    // --- RELATIONS SATELLITES (HasMany) ---

    public function avances(): HasMany
    {
        return $this->hasMany(Avance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
