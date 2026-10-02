<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('conversations', 'external_company_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('external_company_id', 64)->nullable();
                $table->index(
                    ['external_company_id', 'user_id', 'id'],
                    'conversations_external_company_index',
                );
            });
        }

        $this->copyLegacyCompanyIds();
    }

    // The old "tijaraq_company_id" custom attribute stays as a read-only
    // display for agents, the column is now the source of truth.
    protected function copyLegacyCompanyIds(): void
    {
        $attributeId = DB::table('attributes')
            ->where('key', 'tijaraq_company_id')
            ->where('type', 'conversation')
            ->value('id');

        if (!$attributeId) {
            return;
        }

        DB::table('attributables')
            ->where('attribute_id', $attributeId)
            ->where('attributable_type', 'conversation')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('conversations')
                    ->where('id', $row->attributable_id)
                    ->whereNull('external_company_id')
                    ->update([
                        'external_company_id' => substr(
                            trim((string) $row->value, '"'),
                            0,
                            64,
                        ),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_external_company_index');
            $table->dropColumn('external_company_id');
        });
    }
};
