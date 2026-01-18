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
        Schema::create('avances', function (Blueprint $table) {
            $table->id ();


            // LIAISON AVEC LA MISSION 
            $table->foreignId('mission_id')->constrained()->onDelete('cascade'); // FK vers missions
            
            // LIAISON AVEC L'AUDITEUR 
            // Qui a enregistré l'avance
            $table->foreignId('executed_by_id')->constrained('users');
            
            // DONNÉES FINANCIÈRES
            $table->decimal('montant', 10, 2); 
            $table->enum('type_operation', ['paiement', 'regularisation', 'remboursement']); 
            $table->dateTime('date_operation');
            $table->string('numero_piece_paiement', 100)->nullable();
           


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avances');
    }
};
