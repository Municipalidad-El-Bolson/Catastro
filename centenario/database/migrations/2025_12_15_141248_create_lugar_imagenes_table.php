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
        Schema::create('lugar_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lugar_id')->constrained('lugares')->cascadeOnDelete();
            $table->string('path');
            $table->string('titulo')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index(['lugar_id', 'orden']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lugar_imagenes');
    }
};
