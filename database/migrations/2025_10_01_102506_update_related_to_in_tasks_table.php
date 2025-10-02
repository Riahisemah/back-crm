<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Nouveau champ qui référence users
            $table->unsignedBigInteger('related_to_user_id')->nullable()->after('related_to');

            // Relation avec users
            $table->foreign('related_to_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
   public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['related_to_user_id']);
            $table->dropColumn('related_to_user_id');
        });
    }
};
