<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lugar_imagenes', function (Blueprint $table) {
            if (!Schema::hasColumn('lugar_imagenes', 'drive_file_id')) {
                $table->string('drive_file_id')->nullable()->after('path');
            }
            if (!Schema::hasColumn('lugar_imagenes', 'kind')) {
                $table->string('kind')->nullable()->after('drive_file_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lugar_imagenes', function (Blueprint $table) {
            if (Schema::hasColumn('lugar_imagenes', 'drive_file_id')) {
                $table->dropColumn('drive_file_id');
            }
            if (Schema::hasColumn('lugar_imagenes', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }

};
