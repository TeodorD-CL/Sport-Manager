<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

// Production is seeded once and not again, so seeder fixes reach it through here:
// the demo customer showed up on reviews as "Test User", and Football Court A had a basketball photo.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('email', 'user@test.com')->where('name', 'Test User')->update(['name' => 'Stefan Ilievski']);

        DB::table('courts')
            ->where('name', 'Football Court A')
            ->where('image_path', 'like', '%photo-1546519638-68e109498ffc%')
            ->update(['image_path' => '/images/football-pitch.jpg']);
    }

    public function down(): void
    {
    }
};
