<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_companies', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('status')->index();
        });

        // Isi data lama dari activity lead/opportunity dan task activity.
        DB::table('account_companies')->select('id')->chunkById(200, function ($accounts) {
            foreach ($accounts as $account) {
                $activityAt = DB::table('activities as a')
                    ->leftJoin('leads as l', 'l.id', '=', 'a.lead_id')
                    ->leftJoin('opportunities as o', 'o.id', '=', 'a.opportunity_id')
                    ->where(function ($q) use ($account) {
                        $q->where('l.account_companies_id', $account->id)
                            ->orWhere('o.account_companies_id', $account->id);
                    })
                    ->max('a.created_at');

                $taskAt = DB::table('task_activities as ta')
                    ->join('tasks as t', 't.id', '=', 'ta.task_id')
                    ->leftJoin('leads as l', 'l.id', '=', 't.lead_id')
                    ->leftJoin('opportunities as o', 'o.id', '=', 't.opportunity_id')
                    ->leftJoin('account_contacts as c', 'c.id', '=', 't.account_contact_id')
                    ->leftJoin('projects as p', 'p.id', '=', 't.project_id')
                    ->leftJoin('activities as sa', 'sa.id', '=', 't.activity_id')
                    ->leftJoin('leads as sal', 'sal.id', '=', 'sa.lead_id')
                    ->leftJoin('opportunities as sao', 'sao.id', '=', 'sa.opportunity_id')
                    ->where(function ($q) use ($account) {
                        $q->where('l.account_companies_id', $account->id)
                            ->orWhere('o.account_companies_id', $account->id)
                            ->orWhere('c.account_companies_id', $account->id)
                            ->orWhere('p.company_id', $account->id)
                            ->orWhere('sal.account_companies_id', $account->id)
                            ->orWhere('sao.account_companies_id', $account->id);
                    })
                    ->max('ta.created_at');

                $lastActivityAt = collect([$activityAt, $taskAt])->filter()->max();

                if ($lastActivityAt) {
                    DB::table('account_companies')
                        ->where('id', $account->id)
                        ->update(['last_activity_at' => $lastActivityAt]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_companies', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn('last_activity_at');
        });
    }
};
