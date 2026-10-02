<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'external_source')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('external_source', 32)->nullable();
            $table->string('external_user_id', 64)->nullable();
            $table->string('external_company_id', 64)->nullable();

            $table->unique(
                ['external_source', 'external_user_id'],
                'users_external_identity_unique',
            );
            $table->index(
                ['external_source', 'external_company_id'],
                'users_external_company_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_external_identity_unique');
            $table->dropIndex('users_external_company_index');
            $table->dropColumn([
                'external_source',
                'external_user_id',
                'external_company_id',
            ]);
        });
    }
};
