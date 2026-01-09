<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            // Add only the columns that are missing
            if (!Schema::hasColumn('email_campaigns', 'failed_count')) {
                $table->integer('failed_count')->default(0)->after('sent_count');
            }
            
            if (!Schema::hasColumn('email_campaigns', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
            
            if (!Schema::hasColumn('email_campaigns', 'last_processed_at')) {
                $table->timestamp('last_processed_at')->nullable()->after('cancelled_at');
            }
            
            // Add index for performance
            $table->index(['status', 'schedule_time']);
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            // Remove the index
            $table->dropIndex(['status', 'schedule_time']);
            
            // Remove columns we added
            $columnsToDrop = ['failed_count', 'cancelled_at', 'last_processed_at'];
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('email_campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};