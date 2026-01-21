<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();

            // --- LIENS UTILISATEURS / WORKFLOW ---
            $table->foreignId('demandeur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('validation_ch_id')->nullable()->constrained('users'); // Chef Hiérarchique
            $table->foreignId('validation_raf_id')->nullable()->constrained('users'); // RAF
            $table->foreignId('validation_cp_id')->nullable()->constrained('users'); // Chef de Projet

            // --- INFORMATIONS DE MISSION ---
            $table->string('objet', 255)->nullable();
            $table->string('destination', 255)->nullable();
            $table->string('moyen_deplacement', 100)->nullable();
            $table->text('motif')->nullable();

            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            
            // --- SUIVI FINANCIER ---
            $table->decimal('montant_avance_demande', 10, 2)->nullable();
            $table->decimal('montant_total_justifie', 10, 2)->nullable();
            $table->decimal('reliquat_a_rembourser', 10, 2)->nullable();

           $table->enum('statut_actuel', [
                'brouillon',
                'en_attente_ch',
                'valide_ch',
                'valide_raf',
                'valide_cp',
                'avance_payee',
                'en_cours',
                'cloturee'
                ])->default('brouillon');


              $table->boolean('pj_regularise')->default(false);

            // ✅ quand ACCP a marqué la régularisation
            $table->dateTime('date_pj_regularise')->nullable();

            // ✅ note libre (ex: "PJ OK", "Manque facture hôtel", etc.)
            $table->text('note_regularisation')->nullable();
            


            // $table->timestamp('date_cloture')->nullable();
            // $table->dateTime('date_echeance_audit')->nullable();
            // $table->dateTime('date_regularisation')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
