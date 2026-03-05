<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Create a temporary table with the new schema
        Schema::create('notifications_new', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->timestamps();

            // Add indexes if you had them
            $table->index(['notifiable_type', 'notifiable_id']);
        });

        // 2. Copy data (generate new UUIDs for id)
        DB::statement("
            INSERT INTO notifications_new (
                id,
                type,
                data,
                read_at,
                notifiable_type,
                notifiable_id,
                created_at,
                updated_at
            )
            SELECT
                LOWER(HEX(RANDOMBLOB(16))),
                type,
                data,
                read_at,
                notifiable_type,
                notifiable_id,
                created_at,
                updated_at
            FROM notifications
        ");

        // 3. Drop old table
        Schema::dropIfExists('notifications');

        // 4. Rename new table to original name
        Schema::rename('notifications_new', 'notifications');
    }

    public function down(): void
    {
        // Reverse: recreate with integer id
        Schema::create('notifications_old', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });

        DB::statement("
            INSERT INTO notifications_old (
                id,
                type,
                data,
                read_at,
                notifiable_type,
                notifiable_id,
                created_at,
                updated_at
            )
            SELECT
                (row_number() OVER (ORDER BY id)) AS id,
                type,
                data,
                read_at,
                notifiable_type,
                notifiable_id,
                created_at,
                updated_at
            FROM notifications
        ");

        Schema::dropIfExists('notifications');
        Schema::rename('notifications_old', 'notifications');
    }
};