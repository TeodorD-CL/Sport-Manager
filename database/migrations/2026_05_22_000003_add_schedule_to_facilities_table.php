<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->unsignedTinyInteger('opening_hour')->default(8)->after('image_path');
            $table->unsignedTinyInteger('closing_hour')->default(22)->after('opening_hour');
        });

        DB::table('facilities')
            ->whereNull('opening_hour')
            ->update(['opening_hour' => 8]);

        DB::table('facilities')
            ->whereNull('closing_hour')
            ->update(['closing_hour' => 22]);
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['opening_hour', 'closing_hour']);
        });
    }
};
