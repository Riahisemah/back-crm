// Create migration: php artisan make:migration create_email_logs_table

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organisation_id')->constrained()->onDelete('cascade');
            $table->string('to_email');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('message_id')->nullable(); // Gmail message ID
            $table->string('status'); // sent, failed, scheduled
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamps();
            
            $table->index(['lead_id', 'sent_at']);
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_logs');
    }
};