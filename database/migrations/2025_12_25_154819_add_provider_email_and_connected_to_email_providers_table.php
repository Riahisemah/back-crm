<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_providers', function (Blueprint $table) {
            $table->string('provider_email')->nullable();
            $table->boolean('connected')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('email_providers', function (Blueprint $table) {
            $table->dropColumn('provider_email');
            $table->dropColumn('connected');
        });
    }
};
