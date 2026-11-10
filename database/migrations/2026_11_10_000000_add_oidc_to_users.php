<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'oidc_sub')) {
                $table->string('oidc_sub')->nullable()->index()->after('email');
            }
            if (!Schema::hasColumn('users', 'oidc_provider')) {
                $table->string('oidc_provider')->nullable()->after('oidc_sub');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'oidc_provider')) {
                $table->dropColumn('oidc_provider');
            }
            if (Schema::hasColumn('users', 'oidc_sub')) {
                $table->dropColumn('oidc_sub');
            }
        });
    }
};
