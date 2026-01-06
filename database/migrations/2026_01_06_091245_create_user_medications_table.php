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
        Schema::create('user_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('rxcui');
            $table->timestamps();

            // Foreign key to drug_snapshots
            $table->foreign('rxcui')->references('rxcui')->on('drug_snapshots')->onDelete('cascade');

            $table->unique(['user_id', 'rxcui']);
            $table->index('rxcui');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_medications');
    }
};
