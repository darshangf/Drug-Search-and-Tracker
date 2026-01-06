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
        Schema::create('drug_snapshots', function (Blueprint $table) {
            $table->string('rxcui')->primary();
            $table->string('drug_name');
            $table->json('ingredient_base_names')->nullable();
            $table->json('dosage_forms')->nullable();
            $table->timestamp('last_synced_at');
            $table->timestamps();
            
            $table->index('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_snapshots');
    }
};
