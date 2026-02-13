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
        Schema::table('properties', function (Blueprint $table) {
            // Hacer lat y lng nullable para permitir creación sin ubicación
            $table->string('lat')->nullable()->change();
            $table->string('lng')->nullable()->change();

            $table->longText('image_url')->nullable()->change();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Revertir a NOT NULL
            $table->string('lat')->nullable(false)->change();
            $table->string('lng')->nullable(false)->change();
            $table->text('image_url')->nullable()->change();
        });
    }
};
