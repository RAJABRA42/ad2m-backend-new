<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $mission_id
 * @property int $performed_by_id
 * @property string $action_type
 * @property string $description
 * @property array<array-key, mixed>|null $old_data
 * @property array<array-key, mixed>|null $new_data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Mission $mission
 * @property-read \App\Models\User $performedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereActionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereMissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereNewData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereOldData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity wherePerformedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereUpdatedAt($value)
 */
	class Activity extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $mission_id
 * @property int $executed_by_id
 * @property float $montant
 * @property string $type_operation
 * @property \Illuminate\Support\Carbon $date_operation
 * @property string|null $numero_piece_paiement
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $executedBy
 * @property-read \App\Models\Mission $mission
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereDateOperation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereExecutedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereMissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereMontant($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereNumeroPiecePaiement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereTypeOperation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Avance whereUpdatedAt($value)
 */
	class Avance extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $mission_id
 * @property int $uploaded_by_id
 * @property string $type_document
 * @property string $chemin_fichier
 * @property string $nom_original
 * @property string $file_mime_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Mission $mission
 * @property-read \App\Models\User $uploadedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereCheminFichier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereFileMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereMissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereNomOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereTypeDocument($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUploadedById($value)
 */
	class Document extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $demandeur_id
 * @property int|null $validation_ch_id
 * @property int|null $validation_raf_id
 * @property int|null $validation_cp_id
 * @property string|null $objet
 * @property string|null $destination
 * @property string|null $moyen_deplacement
 * @property \Illuminate\Support\Carbon|null $date_debut
 * @property \Illuminate\Support\Carbon|null $date_fin
 * @property string|null $montant_avance_demande
 * @property string|null $montant_total_justifie
 * @property string|null $reliquat_a_rembourser
 * @property string $statut_actuel
 * @property \Illuminate\Support\Carbon|null $date_echeance_audit
 * @property \Illuminate\Support\Carbon|null $date_regularisation
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Avance> $avances
 * @property-read int|null $avances_count
 * @property-read \App\Models\User $demandeur
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\User|null $validateurCH
 * @property-read \App\Models\User|null $validateurCP
 * @property-read \App\Models\User|null $validateurRAF
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereDateDebut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereDateEcheanceAudit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereDateFin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereDateRegularisation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereDemandeurId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereDestination($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereMontantAvanceDemande($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereMontantTotalJustifie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereMoyenDeplacement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereObjet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereReliquatARembourser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereStatutActuel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereValidationChId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereValidationCpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mission whereValidationRafId($value)
 */
	class Mission extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $matricule
 * @property string $nom
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $telephone
 * @property string|null $unite
 * @property string $password
 * @property string $status
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activitesRealisees
 * @property-read int|null $activites_realisees_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Avance> $avancesExecutees
 * @property-read int|null $avances_executees_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documentsTeleverses
 * @property-read int|null $documents_televerses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Mission> $missionDemandees
 * @property-read int|null $mission_demandees_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Mission> $missionsValideesCP
 * @property-read int|null $missions_validees_c_p_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Mission> $missionsValideesRAF
 * @property-read int|null $missions_validees_r_a_f_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Mission> $missionsvalideesCH
 * @property-read int|null $missionsvalidees_c_h_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMatricule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTelephone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUnite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

