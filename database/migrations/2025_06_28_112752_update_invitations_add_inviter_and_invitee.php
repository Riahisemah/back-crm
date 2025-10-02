<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('invitations', function (Blueprint $table) {
        $table->foreignId('inviter_id')->constrained('users')->after('id');
        $table->foreignId('invitee_id')->nullable()->constrained('users')->after('inviter_id');
        $table->dropColumn('email');
    });
}

public function down()
{
    Schema::table('invitations', function (Blueprint $table) {
        $table->dropForeign(['inviter_id']);
        $table->dropForeign(['invitee_id']);
        $table->dropColumn(['inviter_id', 'invitee_id']);
        $table->string('email')->unique()->after('id'); 
    });
}

};
