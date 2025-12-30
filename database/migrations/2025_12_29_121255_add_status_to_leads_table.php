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
    Schema::table('leads', function (Blueprint $table) {
        $table->string('status')
            ->default('to_be_treated')
            ->after('organisation_id');
    });
}

public function down()
{
    Schema::table('leads', function (Blueprint $table) {
        $table->dropColumn('status');
    });
}

};
