<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['type', 'channel', 'id'], 'conversations_chat_list_index');
            $table->index(['user_id', 'type', 'channel', 'id'], 'conversations_user_chat_list_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_chat_list_index');
            $table->dropIndex('conversations_user_chat_list_index');
        });
    }
};
