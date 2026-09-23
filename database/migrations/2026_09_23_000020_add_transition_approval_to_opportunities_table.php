<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->enum('close_loss_status', ['pending', 'approved'])->nullable()->after('stage_id');
            $table->text('close_loss_note')->nullable()->after('loss_reasons_id');
            $table->timestamp('close_loss_requested_at')->nullable()->after('close_loss_note');
            $table->unsignedBigInteger('close_loss_approved_by')->nullable()->after('close_loss_requested_at');
            $table->timestamp('close_loss_approved_at')->nullable()->after('close_loss_approved_by');

            $table->enum('negotiation_status', ['pending', 'approved'])->nullable()->after('close_loss_approved_at');
            $table->timestamp('negotiation_requested_at')->nullable()->after('negotiation_status');
            $table->unsignedBigInteger('negotiation_approved_by')->nullable()->after('negotiation_requested_at');
            $table->timestamp('negotiation_approved_at')->nullable()->after('negotiation_approved_by');

            $table->foreign('close_loss_approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('negotiation_approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['negotiation_approved_by']);
            $table->dropForeign(['close_loss_approved_by']);
            $table->dropColumn([
                'close_loss_status',
                'close_loss_note',
                'close_loss_requested_at',
                'close_loss_approved_by',
                'close_loss_approved_at',
                'negotiation_status',
                'negotiation_requested_at',
                'negotiation_approved_by',
                'negotiation_approved_at',
            ]);
        });
    }
};