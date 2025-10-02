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
    Schema::create('opportunities', function (Blueprint $table) {
        $table->id();
        $table->foreignId('organisation_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->string('company');
        $table->decimal('value', 15, 2);
        $table->string('stage');
        $table->unsignedTinyInteger('probability'); // e.g. 0 to 100
        $table->date('close_date');
        $table->string('contact');
        $table->longText('description')->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
