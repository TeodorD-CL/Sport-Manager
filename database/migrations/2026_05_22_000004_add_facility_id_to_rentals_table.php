<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->uuid('facility_id')->nullable()->after('id');
            $table->foreign('facility_id')->references('id')->on('facilities')->nullOnDelete();
            $table->index('facility_id');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['facility_id']);
            $table->dropIndex(['facility_id']);
            $table->dropColumn('facility_id');
        });
    }
};
