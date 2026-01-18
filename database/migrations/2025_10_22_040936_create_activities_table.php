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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            // --- LIAISONS ---
            $table->foreignId('mission_id')->constrained()->onDelete('cascade');    
            $table->foreignId('performed_by_id')->constrained('users')->onDelete('cascade'); 

            // --- INFORMATION DE L'ACTION ---
            $table->enum('action_type', [
                'soumission', 'validation_ch', 'validation_raf', 'validation_cp',
                'paiement_avance', 'regularisation', 'om_redige', 
                'cloture', 'rejet', 'modification_dm', 'document_televerse', 'rappel_48h'
            ]);
            
        
            $table->text('description');

            // --- TRACABILITÉ DES DONNÉES
            $table->json('old_data')->nullable(); 
            $table->json('new_data')->nullable(); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
