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
       Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('type')->nullable();
    $table->string('priority')->nullable();
    $table->string('status')->default('open');
    $table->date('due_date')->nullable();
    $table->string('related_to')->nullable();
    
    $table->timestamps();
});

    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
