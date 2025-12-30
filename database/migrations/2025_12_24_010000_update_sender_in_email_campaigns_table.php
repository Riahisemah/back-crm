<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            // Drop old sender column (varchar)
            if (Schema::hasColumn('email_campaigns', 'sender')) {
                $table->dropColumn('sender');
            }
        });

        Schema::table('email_campaigns', function (Blueprint $table) {
            // Add sender as nullable FIRST
            $table->foreignId('sender')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropForeign(['sender']);
            $table->dropColumn('sender');
        });

        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->string('sender');
        });
    }
};
