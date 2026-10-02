<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tijaraq_idempotency_keys')) {
            Schema::create('tijaraq_idempotency_keys', function (
                Blueprint $table,
            ) {
                $table->id();
                $table->string('external_company_id', 64);
                $table->string('key', 64);
                $table->string('request_hash', 64);
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->json('response_body')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();

                $table->unique(['external_company_id', 'key']);
            });
        }

        if (!Schema::hasTable('tijaraq_audit_logs')) {
            Schema::create('tijaraq_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('external_company_id', 64)->nullable();
                $table->string('external_user_id', 64)->nullable();
                $table->string('action', 64);
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->string('request_id', 64)->nullable();
                $table->string('ip', 45)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['external_company_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('tijaraq_webhook_deliveries')) {
            Schema::create('tijaraq_webhook_deliveries', function (
                Blueprint $table,
            ) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->string('event', 40);
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->json('payload');
                $table->string('status', 16)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('last_status_code')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tijaraq_webhook_deliveries');
        Schema::dropIfExists('tijaraq_audit_logs');
        Schema::dropIfExists('tijaraq_idempotency_keys');
    }
};
