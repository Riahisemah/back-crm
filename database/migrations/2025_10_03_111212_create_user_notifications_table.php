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
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->enum('type', ['info','success','warning','error','reminder'])->default('info');
            $table->timestamp('timestamp'); // when the event occurred (use now())
            $table->boolean('read')->default(false);
            $table->string('avatar')->nullable();
            $table->json('related_view')->nullable(); // e.g. {"name":"task","id":123}
            $table->json('action_data')->nullable(); // any action payload
            $table->enum('category', ['task','appointment','opportunity','contact','system','reminder'])->default('system');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_notifications');
    }
};
