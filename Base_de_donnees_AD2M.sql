---- Crée la table de base pour l'authentification et l'identification des employés
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Clé primaire unique et auto-incrémentée
    
    -- CHAMPS D'IDENTIFICATION SGMS
    matricule VARCHAR(50) UNIQUE NOT NULL,      -- Matricule de l'employé (identifiant unique pour le SGMS)
    nom VARCHAR(150) NOT NULL,                  -- Nom complet de l'employé (pour les rapports/OM)
    unite VARCHAR(100) NULL,                    -- Unité ou Département de l'employé (pour le filtrage)
    telephone VARCHAR(50) NULL,                 -- Numéro de contact
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active', -- Statut du compte (actif/inactif)
    
    -- CHAMPS LARAVEL AUTH
    name VARCHAR(255) NOT NULL,                 -- Nom d'affichage simple (pour compatibilité Laravel Auth)
    email VARCHAR(150) UNIQUE NOT NULL,         -- Adresse email unique (identifiant de connexion)
    email_verified_at TIMESTAMP NULL,           -- Date de vérification de l'email
    password VARCHAR(255) NOT NULL,             -- Mot de passe haché
    remember_token VARCHAR(100) NULL,           -- Jeton de "Se souvenir de moi"
    
    created_at TIMESTAMP NULL,                  -- Date et heure de création de l'enregistrement
    updated_at TIMESTAMP NULL                   -- Date et heure de la dernière modification
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;







-- Table pour définir les rôles fonctionnels (ACCP, RAF, Missionnaire, etc.)
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Clé primaire du rôle
    name VARCHAR(50) UNIQUE NOT NULL,           -- Nom technique du rôle (Ex: 'accp'). Utilisé dans le code.
    display_name VARCHAR(100) NOT NULL,         -- Nom affiché du rôle (Ex: 'Agent Comptable').
    description TEXT NULL,                      -- Description détaillée du rôle
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Table pivot pour la relation Plusieurs-à-Plusieurs entre USERS et ROLES
CREATE TABLE role_user (
  
    role_id BIGINT UNSIGNED NOT NULL,           -- ID du rôle (FK vers roles.id)
    
    created_at TIMESTAMP NULL,                  -- Date et heure d'attribution du rôle
    updated_at TIMESTAMP NULL,
    
    PRIMARY KEY (user_id, role_id),             -- Clé primaire composite (garantit l'unicité de la liaison)
    
    -- DÉFINITION DES CLÉS ÉTRANGÈRES
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;



-- Table centrale pour le suivi des Demandes et Ordres de Mission
CREATE TABLE missions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Clé primaire (N° d'ordre de la mission)
    
    -- LIAISONS UTILISATEURS / AUDIT TRAIL
    demandeur_id BIGINT UNSIGNED NOT NULL,      -- Qui a demandé la mission (FK vers users.id)
    validation_ch_id BIGINT UNSIGNED NULL,      -- Qui a validé comme Chef Hiérarchique (FK vers users.id)
    validation_raf_id BIGINT UNSIGNED NULL,     -- Qui a validé comme RAF (FK vers users.id)
    validation_cp_id BIGINT UNSIGNED NULL,      -- Qui a validé comme Chef de Projet (FK vers users.id)
    
    -- INFORMATIONS DE MISSION
    objet VARCHAR(255) NULL,                    -- Objet de la mission
    destination VARCHAR(255) NULL,              -- Lieu de mission
    moyen_deplacement VARCHAR(100) NULL,        -- Moyen de déplacement utilisé
    date_debut DATE NULL,                       -- Date de départ
    date_fin DATE NULL,                         -- Date de retour (critique pour le calcul des 48h)
    
    -- SUIVI FINANCIER (DONNÉES CLES)
    montant_avance_demande DECIMAL(10, 2) NULL, -- Avance demandée par le missionnaire
    montant_total_justifie DECIMAL(10, 2) NULL, -- Montant final justifié par le missionnaire
    reliquat_a_rembourser DECIMAL(10, 2) NULL,  -- Montant calculé à rembourser/à payer
    
    -- AUDIT ET STATUT
    statut_actuel ENUM('Brouillon', 'Soumis', 'Validé', 'OM Rédigé', 'Avance Payée', 'Avance Échue', 'Justifié', 'Clôturé') NOT NULL DEFAULT 'Brouillon', -- Statut actuel du workflow
    date_echeance_audit DATETIME NULL,          -- Date fin mission + 48h (pour l'alerte d'audit)
    date_regularisation DATETIME NULL,          -- Date de la clôture finale/régularisation
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    -- DÉFINITION DES CLÉS ÉTRANGÈRES
    FOREIGN KEY (demandeur_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (validation_ch_id) REFERENCES users(id),
    FOREIGN KEY (validation_raf_id) REFERENCES users(id),
    FOREIGN KEY (validation_cp_id) REFERENCES users(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;





-- Table pour l'historique des transactions financières liées aux avances de mission
CREATE TABLE avances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- LIAISONS
    mission_id BIGINT UNSIGNED NOT NULL,        -- Mission concernée (FK vers missions.id)
    executed_by_id BIGINT UNSIGNED NOT NULL,    -- Qui (ACCP/ACTB) a enregistré cette transaction (FK vers users.id)
    
    -- DONNÉES DE LA TRANSACTION
    montant DECIMAL(10, 2) NOT NULL,            -- Montant de la transaction (paiement, régularisation, remboursement)
    type_operation ENUM('paiement', 'regularisation', 'remboursement') NOT NULL, -- Nature de l'opération
    date_operation DATETIME NOT NULL,           -- Date réelle de l'opération
    numero_piece_paiement VARCHAR(100) NULL,    -- N° de Pièce Comptable (CK/BR)
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    -- DÉFINITION DES CLÉS ÉTRANGÈRES
    FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
    FOREIGN KEY (executed_by_id) REFERENCES users(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;




-- Table pour le référencement des fichiers téléversés (DM signée, OM, Justificatifs)
CREATE TABLE documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- LIAISONS
    mission_id BIGINT UNSIGNED NOT NULL,        -- Mission à laquelle le document est rattaché (FK vers missions.id)
    uploaded_by_id BIGINT UNSIGNED NOT NULL,    -- Qui a téléversé le document (Audit - FK vers users.id)

    -- METADONNÉES
    type_document ENUM('DM', 'OM', 'Piece Paiement', 'Justificatif', 'Autre') NOT NULL, -- Type de document pour le workflow
    chemin_fichier VARCHAR(255) NOT NULL,       -- Chemin d'accès au fichier sur le serveur de stockage
    nom_original VARCHAR(255) NOT NULL,         -- Nom du fichier avant l'upload
    file_mime_type VARCHAR(100) NOT NULL,       -- Type MIME du fichier (Ex: application/pdf)
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    -- DÉFINITION DES CLÉS ÉTRANGÈRES
    FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;



-- Table pour l'historique complet des actions (Audit Trail)
CREATE TABLE activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- LIAISONS
    mission_id BIGINT UNSIGNED NOT NULL,        -- Mission concernée par l'événement (FK vers missions.id)
    performed_by_id BIGINT UNSIGNED NOT NULL,   -- Qui a exécuté l'action (Audit - FK vers users.id)

    -- INFORMATION DE L'ACTION
    action_type ENUM('soumission', 'validation_ch', 'validation_raf', 'validation_cp', 'paiement_avance', 'regularisation', 'om_redige', 'cloture', 'rejet', 'modification_dm', 'document_televerse', 'rappel_48h') NOT NULL, -- Nature de l'événement
    description TEXT NOT NULL,                  -- Description textuelle de l'événement
    
    -- TRACABILITÉ DES DONNÉES
    old_data JSON NULL,                         -- Anciennes valeurs des champs modifiés (si applicable)
    new_data JSON NULL,                         -- Nouvelles valeurs des champs modifiés (si applicable)

    created_at TIMESTAMP NULL,                  -- Date et heure de l'événement (Audit Time)
    updated_at TIMESTAMP NULL,
    
    -- DÉFINITION DES CLÉS ÉTRANGÈRES
    FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;











<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée (BIGINT UNSIGNED)
            
            // --- CHAMPS D'IDENTIFICATION SGMS (Critiques) ---
            $table->string('matricule', 50)->unique(); // ID unique et stable de l'employé (Audit/RH)
            $table->string('nom', 150);                // Nom complet de l'employé (pour les rapports/OM)
            $table->string('unite', 100)->nullable();   // Unité ou Département de l'employé (Filtre de mission)
            $table->string('telephone', 50)->nullable(); // Numéro de contact de l'employé
            $table->enum('status', ['active', 'inactive'])->default('active'); // Permet de désactiver un compte sans le supprimer (Audit)
            
            // --- CHAMPS LARAVEL AUTH (Conservés pour compatibilité) ---
            $table->string('name'); // Nom d'affichage simple (pour le système d'authentification par défaut)
            $table->string('email', 150)->unique(); // Identifiant de connexion unique
            $table->timestamp('email_verified_at')->nullable(); // Date de vérification de l'email
            $table->string('password'); // Mot de passe haché
            $table->rememberToken(); // Jeton pour la fonction "Se souvenir de moi"
            
            $table->timestamps(); // created_at et updated_at (Date de création et dernière modification de l'enregistrement)
        });

        // Les tables 'password_reset_tokens' et 'sessions' sont incluses par défaut par Laravel,
        // et nécessaires pour la récupération de mot de passe et la gestion des sessions.
        // (Leur structure par défaut est conservée ici).
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- TABLE PRINCIPALE DES RÔLES ---
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); // Clé Primaire du Rôle
            $table->string('name', 50)->unique(); // Nom technique du rôle (Ex: 'accp', 'raf'). Utilisé dans le code.
            $table->string('display_name', 100); // Nom affiché du rôle (Ex: 'Agent Comptable', 'RAF'). Plus convivial.
            $table->text('description')->nullable(); // Description du rôle pour l'administrateur système.
            $table->timestamps();
        });
        
        // --- TABLE PIVOT (LIAISON UTILISATEUR/RÔLE) ---
        Schema::create('role_user', function (Blueprint $table) {
            // Clés étrangères vers les tables 'users' et 'roles'
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Si l'utilisateur est supprimé, la liaison est supprimée
            $table->foreignId('role_id')->constrained()->onDelete('cascade'); // Si le rôle est supprimé, la liaison est supprimée
            
            $table->timestamps(); 
            
            // Clé primaire composite : garantit qu'un utilisateur n'a pas deux fois le même rôle
            $table->primary(['user_id', 'role_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id(); // Clé primaire (N° d'ordre)
            
            // --- LIAISONS UTILISATEURS / WORKFLOW (AUDIT TRAIL) ---
            $table->foreignId('demandeur_id')->constrained('users')->onDelete('cascade'); // Qui a créé/demandé la mission (Référence à users.id)
            $table->foreignId('validation_ch_id')->nullable()->constrained('users'); // Qui a validé comme Chef Hiérarchique (ID users.id)
            $table->foreignId('validation_raf_id')->nullable()->constrained('users'); // Qui a validé comme RAF (ID users.id)
            $table->foreignId('validation_cp_id')->nullable()->constrained('users'); // Qui a validé comme Chef de Projet (ID users.id)
            
            // --- INFORMATIONS DE MISSION ---
            $table->string('objet', 255)->nullable(); // Objet de la mission (Ex: 'Participation à la passation de service')
            $table->string('destination', 255)->nullable(); // Lieu de mission
            $table->string('moyen_deplacement', 100)->nullable(); // Ex: Avion, Voiture de projet, Taxi Brousse
            $table->date('date_debut')->nullable(); // Date de départ
            $table->date('date_fin')->nullable(); // Date de retour (Critique pour le calcul des 48h)
            
            // --- SUIVI FINANCIER (DONNÉES CLES) ---
            $table->decimal('montant_avance_demande', 10, 2)->nullable(); // Avance demandée par le missionnaire
            $table->decimal('montant_total_justifie', 10, 2)->nullable(); // Montant final justifié par le missionnaire (Clôture)
            $table->decimal('reliquat_a_rembourser', 10, 2)->nullable(); // Montant final à rembourser/à payer (calculé)
            
            // --- AUDIT ET STATUT (FLUX DE TRAVAIL) ---
            $table->enum('statut_actuel', [
                'Brouillon', 'Soumis', 'Validé', 'OM Rédigé',
                'Avance Payée', 'Avance Échue', 'Justifié', 'Clôturé'
            ])->default('Brouillon'); // Statut actuel de la mission (Ex: Soumis, Validé, Clôturé)
            
            $table->dateTime('date_echeance_audit')->nullable(); // Date fin mission + 48h (pour l'audit/alerte)
            $table->dateTime('date_regularisation')->nullable(); // Date de la régularisation finale
            
            $table->timestamps(); // created_at et updated_at (Date de création de la DM / dernière modification)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avances', function (Blueprint $table) {
            $table->id(); 
            
            // LIAISON AVEC LA MISSION
            $table->foreignId('mission_id')->constrained()->onDelete('cascade'); // À quelle mission cette transaction se rapporte-t-elle ?
            
            // LIAISON AVEC L'AUDITEUR/COMPTABLE
            $table->foreignId('executed_by_id')->constrained('users'); // Qui (ACCP/ACTB) a enregistré cette transaction ?
            
            // DONNÉES DE LA TRANSACTION
            $table->decimal('montant', 10, 2); // Montant de la transaction (paiement, régularisation, remboursement)
            $table->enum('type_operation', ['paiement', 'regularisation', 'remboursement']); // Nature de l'opération
            $table->dateTime('date_operation'); // Date réelle de l'opération (Date de Paiement/Régularisation)
            $table->string('numero_piece_paiement', 100)->nullable(); // N° de Pièce Comptable (CK/BR) associé à cette transaction.
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avances');
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id(); 
            
            // LIAISONS
            $table->foreignId('mission_id')->constrained()->onDelete('cascade'); // À quelle mission le document est rattaché
            $table->foreignId('uploaded_by_id')->constrained('users')->onDelete('cascade'); // Qui a téléversé le document (Audit)

            // METADONNÉES DU DOCUMENT
            $table->enum('type_document', [
                'DM',           // Demande de Mission (validée/signée)
                'OM',           // Ordre de Mission (validé/signé)
                'Piece Paiement', // Pièce comptable (CK/BR)
                'Justificatif', // Factures, Billets, Fiches de présence, etc.
                'Autre'
            ]);
            
            $table->string('chemin_fichier', 255); // Le chemin d'accès au fichier sur le serveur (ex: 'storage/missions/123/om_signe.pdf')
            $table->string('nom_original', 255); // Nom du fichier tel qu'il a été téléchargé par l'utilisateur
            $table->string('file_mime_type', 100); // Type de fichier (Sécurité/Affichage)
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id(); 

            // --- LIAISONS ---
            $table->foreignId('mission_id')->constrained()->onDelete('cascade'); // Mission concernée par l'action
            $table->foreignId('performed_by_id')->constrained('users')->onDelete('cascade'); // Qui a fait l'action (Audit)

            // --- INFORMATION DE L'ACTION ---
            $table->enum('action_type', [
                'soumission', 'validation_ch', 'validation_raf', 'validation_cp',
                'paiement_avance', 'regularisation', 'om_redige', 
                'cloture', 'rejet', 'modification_dm', 'document_televerse', 'rappel_48h'
            ]); // Type d'action spécifique
            
            $table->text('description'); // Description textuelle complète de l'événement (Ex: "DM soumise pour validation par Rabe")

            // --- TRACABILITÉ DES DONNÉES (POUR LES MODIFICATIONS) ---
            $table->json('old_data')->nullable(); // Anciennes valeurs des champs modifiés (format JSON)
            $table->json('new_data')->nullable(); // Nouvelles valeurs des champs modifiés (format JSON)

            $table->timestamps(); // created_at est la date/heure de l'événement (Audit Time)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
