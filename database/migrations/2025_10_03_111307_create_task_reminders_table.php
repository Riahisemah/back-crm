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
        Schema::create('task_reminders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type'); // e.g. 'due_soon_1d', 'due_soon_1h'
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_reminders');
    }
};
