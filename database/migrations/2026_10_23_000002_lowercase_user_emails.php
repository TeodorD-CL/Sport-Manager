<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PostgreSQL compares text case-sensitively, so Bob@Mail.com and bob@mail.com were two accounts.
// Emails are lowercased on save from now on; this fixes the rows saved before.
return new class extends Migration
{
    public function up(): void
    {
        $clashes = DB::table('users')
            ->selectRaw('lower(email) as email')
            ->groupByRaw('lower(email)')
            ->havingRaw('count(*) > 1')
            ->pluck('email');

        if ($clashes->isNotEmpty()) {
            throw new \RuntimeException('These emails belong to more than one account when case is ignored. Merge or rename them first: ' . $clashes->join(', '));
        }

        DB::table('users')->whereRaw('email <> lower(email)')->update(['email' => DB::raw('lower(email)')]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
        }
    }
};
