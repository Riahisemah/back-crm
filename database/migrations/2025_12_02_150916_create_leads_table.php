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
    Schema::create('leads', function (Blueprint $table) {
        $table->id();

        // Foreign key to organisations
        $table->unsignedBigInteger('organisation_id');
        $table->foreign('organisation_id')
              ->references('id')
              ->on('organisations')
              ->onDelete('cascade');

        // Lead fields
        $table->string('full_name')->nullable();
        $table->string('position')->nullable();
        $table->string('company')->nullable();
        $table->string('location')->nullable();
        $table->string('profile_url')->nullable();
        $table->integer('followers')->default(0);
        $table->integer('connections')->default(0);
        $table->string('education')->nullable();

        // Message fields
        $table->longText('personal_message')->nullable();
        $table->integer('message_length')->nullable();

        // Metadata
        $table->timestamp('generated_at')->nullable();
        $table->integer('total_leads')->default(0);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
