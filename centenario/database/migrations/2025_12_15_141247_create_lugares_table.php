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
        Schema::create('lugares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->nullable()->constrained()->nullOnDelete();

            $table->string('titulo');
            $table->text('descripcion')->nullable();

            // Coordenadas (Mapbox usa lng/lat)
            $table->decimal('lng', 10, 7);
            $table->decimal('lat', 10, 7);

            $table->string('direccion')->nullable();
            $table->string('localidad')->nullable();

            // Para ordenar en un futuro “recorrido”
            $table->integer('orden')->default(0);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'categoria_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lugares');
    }
};
