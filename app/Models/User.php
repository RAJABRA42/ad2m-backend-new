<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Role;
use App\Models\Avance;
use App\Models\Mission;
use App\Models\Activity;
use App\Models\Document;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin \Eloquent
 * Ces lignes aident l'éditeur à reconnaître votre méthode hasRole personnalisée.
 * @method bool hasRole(string|array $roles)
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'matricule',
        'nom',
        'unite',
        'poste',
        'telephone',
        'status',
         'chef_hierarchique_id',
        ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // RELATIONS
    

    //un utilisateur a plusieurs rôles

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }


    // Vérifie si l'utilisateur possède un rôle spécifique

   public function hasRole(string|array $roles): bool
    {
        // Votre logique personnalisée qui fonctionne très bien !
        $userRoles = $this->roles->pluck('name')->all();

        if (is_string($roles)) {

            return in_array($roles, $userRoles);
        }

        return count(array_intersect($userRoles, $roles)) > 0;
    }


    //RELATION CHEF HIERARCHIQUE - SUBORDONNES
    public function chef() : BelongsTo {

        return $this->belongsTo(User::class, 'chef_hierarchique_id');
    }


    public function subordonnes(): HasMany {

        return $this->hasMany (User::class, 'chef_hierarchique_id');


    }





    //un utilisateur a plusieurs  missions en tant que demandeur 
    
    public function missionDemandees(): HasMany
    {
        return $this->hasMany(Mission::class, 'demandeur_id');

    }

    // un utilisateur a plusieurs transactions financières

    public function avancesExecutees(): HasMany

    {
        return $this->hasMany(Avance::class, 'executed_by');
    }

    //un utilisateurs a plusieurs enregistrements d'activités

    public function activitesRealisees():HasMany

    {
        return $this->hasMany(Activity::class, 'performed_by');
    }
    

    //VALIIDATEUR


    // Missions validées par cet utilisateur en tant que Chef Hiérarchique
    public function missionsvalideesCH(): HasMany
    {
        return $this->hasMany(Mission::class,'validation_ch_id');
    }

    //Missions validées par cet utilisateur en tant que RAF
    public function missionsValideesRAF(): HasMany
    {
        return $this->hasMany(Mission::class, 'validation_raf_id');
    }

    //Missions validées par cet utilisateur en tant que Chef de Projet
    public function missionsValideesCP(): HasMany
    {
        return $this->hasMany(Mission::class, 'validation_cp_id');
    }


    //Documents téléversés par cet utilisateur.
    public function documentsTeleverses(): HasMany
    {
    return $this->hasMany(Document::class, 'uploaded_by_id');
    }
}