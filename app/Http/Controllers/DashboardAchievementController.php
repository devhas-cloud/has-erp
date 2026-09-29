<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\Stage;

class DashboardAchievementController extends Controller
{
    public function index()
    {
        $wonStageId = (int) (Stage::where('stage_name', 'Closed Won')->value('id') ?: 5);

        // 1. Total achievement (semua divisi)
        $totalAchievement = Quotation::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotations.opportunity_id')
            ->where('quotations.status', Quotation::STATUS_FINISH)
            ->where('opportunities.stage_id', $wonStageId)
            ->sum('quotations.grand_total');

        // 2. Achievement per divisi (via task -> handling_group -> division)
        $divisions = Quotation::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotations.opportunity_id')
            ->join('tasks', 'tasks.id', '=', 'quotations.task_id')
            ->join('handling_groups', 'handling_groups.id', '=', 'tasks.handling_group_id')
            ->join('divisions', 'divisions.id', '=', 'handling_groups.division_id')
            ->where('quotations.status', Quotation::STATUS_FINISH)
            ->where('opportunities.stage_id', $wonStageId)
            ->groupBy('divisions.id', 'divisions.division_name')
            ->selectRaw(
                'divisions.id as division_id,
                 divisions.division_name,
                 COUNT(DISTINCT quotations.id) as quotation_count,
                 SUM(quotations.grand_total) as total'
            )
            ->orderByDesc('total')
            ->get();

        // 3. Brand terjual per divisi (quotation_items.part_number = master_products.code)
        $brands = Quotation::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotations.opportunity_id')
            ->join('tasks', 'tasks.id', '=', 'quotations.task_id')
            ->join('handling_groups', 'handling_groups.id', '=', 'tasks.handling_group_id')
            ->join('divisions', 'divisions.id', '=', 'handling_groups.division_id')
            ->join('quotation_items', 'quotation_items.quotation_id', '=', 'quotations.id')
            ->join('master_products', 'master_products.code', '=', 'quotation_items.part_number')
            ->where('quotations.status', Quotation::STATUS_FINISH)
            ->where('opportunities.stage_id', $wonStageId)
            ->whereNotNull('master_products.brand')
            ->where('master_products.brand', '!=', '')
            ->groupBy('divisions.id', 'master_products.brand')
            ->selectRaw(
                'divisions.id as division_id,
                 master_products.brand,
                 COUNT(DISTINCT quotation_items.id) as item_count,
                 SUM(quotation_items.qty * quotation_items.price) as total_value'
            )
            ->orderBy('division_id')
            ->orderByDesc('total_value')
            ->get();

        $brandsByDivision = $brands->groupBy('division_id');

        // 4. Pipeline opportunity per divisi (stage 1 = New, 2 = Proposal & Quote, 4 = Negotiation)
        $stageIds = [1, 2, 4];
        $opportunityCounts = Quotation::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotations.opportunity_id')
            ->join('tasks', 'tasks.id', '=', 'quotations.task_id')
            ->join('handling_groups', 'handling_groups.id', '=', 'tasks.handling_group_id')
            ->join('divisions', 'divisions.id', '=', 'handling_groups.division_id')
            ->whereIn('opportunities.stage_id', $stageIds)
            ->groupBy('divisions.id', 'opportunities.stage_id')
            ->selectRaw(
                'divisions.id as division_id,
                 opportunities.stage_id,
                 COUNT(DISTINCT opportunities.id) as total'
            )
            ->get();

        $stageCountsByDivision = [];
        foreach ($opportunityCounts as $row) {
            $stageCountsByDivision[$row->division_id][$row->stage_id] = (int) $row->total;
        }

        return view('dashboard-achievement.index', compact(
            'totalAchievement',
            'divisions',
            'brandsByDivision',
            'stageCountsByDivision'
        ));
    }
}