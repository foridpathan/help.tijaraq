<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('conversations', 'assignee_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->renameColumn('assigned_to', 'assignee_id');
            });
        }

        // RENAME INDEX is unsupported on MariaDB < 10.5.2, so drop and recreate
        $prefix = DB::getTablePrefix();
        $oldIndex = Schema::hasIndex(
            'conversations',
            "{$prefix}tickets_assigned_to_index",
        )
            ? "{$prefix}tickets_assigned_to_index"
            : 'conversations_assigned_to_index';
        $newIndex = str_starts_with($oldIndex, "{$prefix}tickets_")
            ? 'tickets_assignee_id_index'
            : 'conversations_assignee_id_index';

        if (Schema::hasIndex('conversations', $oldIndex)) {
            Schema::table('conversations', function (Blueprint $table) use (
                $oldIndex,
                $newIndex,
            ) {
                $table->dropIndex($oldIndex);
                $table->index('assignee_id', $newIndex);
            });
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table
                ->string('assigned_to', 10)
                ->default('agent')
                ->index()
                ->after('assignee_id');
            $table
                ->boolean('ai_agent_involved')
                ->default(false)
                ->index()
                ->before('group_id');
        });
    }
};
