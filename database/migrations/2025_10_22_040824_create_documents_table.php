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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // LIENS
            $table->foreignId('mission_id')->constrained()->onDelete('cascade');
            $table->foreignId('uploaded_by_id')->constrained('users')->onDelete('cascade'); // Qui a uploadé

            // DONNÉES DU DOCUMENT
            $table->enum('type_document', [
                'DM',           // Demande de Mission (signée)
                'OM',           // Ordre de Mission (signé)
                'Piece Paiement', // Chèque, Bordereau de virement (CK/BR)
                'Justificatif', // Factures, Billets de transport, Fiche de présence, etc.
                'Autre'
            ]);
            
            $table->string('chemin_fichier', 255); // Le chemin d'accès au fichier sur le serveur (ex: 'storage/missions/123/om_signe.pdf')
            $table->string('nom_original', 255); // Le nom du fichier avant l'upload (ex: 'Mon_OM_Signe.pdf')
            $table->string('file_mime_type', 100); // Ex: 'application/pdf', 'image/jpeg'


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
