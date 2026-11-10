<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_contacts', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('status')->index();
        });

        // Isi data lama dari activity lead/opportunity dan task activity.
        DB::table('account_contacts')->select('id')->chunkById(200, function ($contacts) {
            foreach ($contacts as $contact) {
                $activityAt = DB::table('activities as a')
                    ->leftJoin('leads as l', 'l.id', '=', 'a.lead_id')
                    ->leftJoin('opportunities as o', 'o.id', '=', 'a.opportunity_id')
                    ->where(function ($q) use ($contact) {
                        $q->where('l.account_contacts_id', $contact->id)
                            ->orWhere('o.account_contacts_id', $contact->id);
                    })
                    ->max('a.created_at');

                $taskAt = DB::table('task_activities as ta')
                    ->join('tasks as t', 't.id', '=', 'ta.task_id')
                    ->leftJoin('leads as l', 'l.id', '=', 't.lead_id')
                    ->leftJoin('opportunities as o', 'o.id', '=', 't.opportunity_id')
                    ->leftJoin('activities as sa', 'sa.id', '=', 't.activity_id')
                    ->leftJoin('leads as sal', 'sal.id', '=', 'sa.lead_id')
                    ->leftJoin('opportunities as sao', 'sao.id', '=', 'sa.opportunity_id')
                    ->where(function ($q) use ($contact) {
                        $q->where('t.account_contact_id', $contact->id)
                            ->orWhere('l.account_contacts_id', $contact->id)
                            ->orWhere('o.account_contacts_id', $contact->id)
                            ->orWhere('sal.account_contacts_id', $contact->id)
                            ->orWhere('sao.account_contacts_id', $contact->id);
                    })
                    ->max('ta.created_at');

                $lastActivityAt = collect([$activityAt, $taskAt])->filter()->max();

                if ($lastActivityAt) {
                    DB::table('account_contacts')
                        ->where('id', $contact->id)
                        ->update(['last_activity_at' => $lastActivityAt]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_contacts', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn('last_activity_at');
        });
    }
};
