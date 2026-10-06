<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Opportunity;
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

        // 2. Achievement per divisi (via opportunity -> division). Baris divisi
        //    ditentukan daftar tetap [Enviro, Water, IH, Gas, IMS]; divisi tanpa
        //    data tetap tampil dengan nilai 0.
        $divOrder = ['Enviro', 'Water', 'IH', 'Gas', 'IMS'];

        $divisionList = Division::whereIn('division_name', $divOrder)->where('type', 'Internal')
            ->get(['id', 'division_name'])
            ->unique('division_name');

        // Urutkan sesuai urutan daftar (di luar DB agar portabel lintas driver).
        $divisionRank = array_flip($divOrder);
        $divisionList = $divisionList
            ->sortBy(fn ($d) => $divisionRank[$d->division_name] ?? 99)
            ->values();

        $achievementRows = Quotation::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotations.opportunity_id')
            ->join('divisions', 'divisions.id', '=', 'opportunities.division_id')
            ->where('quotations.status', Quotation::STATUS_FINISH)
            ->where('opportunities.stage_id', $wonStageId)
            ->groupBy('divisions.id', 'divisions.division_name')
            ->selectRaw(
                'divisions.id as division_id,
                 divisions.division_name,
                 COUNT(DISTINCT quotations.id) as quotation_count,
                 SUM(quotations.grand_total) as total'
            )
            ->get()
            ->keyBy('division_id');

        $divisions = $divisionList
            ->map(fn ($d) => (object) [
                'division_id' => $d->id,
                'division_name' => $d->division_name,
                'quotation_count' => (int) ($achievementRows[$d->id]->quotation_count ?? 0),
                'total' => (float) ($achievementRows[$d->id]->total ?? 0),
            ])
            ->values();

        // 3. Brand terjual per divisi (quotation_items.part_number = master_products.code)
        $brands = Quotation::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotations.opportunity_id')
            ->join('divisions', 'divisions.id', '=', 'opportunities.division_id')
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

        // 4. Jumlah opportunity per divisi dikelompokkan probabilitas 25% / 50% / 70%.
        //    Hitung SEMUA opportunity milik divisi (tanpa syarat quotation).
        $probabilityRows = Opportunity::query()
            ->join('divisions', 'divisions.id', '=', 'opportunities.division_id')
            ->whereIn('divisions.id', $divisionList->pluck('id'))
            ->whereIn('opportunities.probability', [25, 50, 70])
            ->groupBy('divisions.id', 'opportunities.probability')
            ->selectRaw(
                'divisions.id as division_id,
                 opportunities.probability,
                 COUNT(*) as total'
            )
            ->get();

        $probabilityCountsByDivision = [];
        foreach ($probabilityRows as $row) {
            $probabilityCountsByDivision[$row->division_id][(int) $row->probability] = (int) $row->total;
        }

        return view('dashboard-achievement.index', compact(
            'totalAchievement',
            'divisions',
            'brandsByDivision',
            'probabilityCountsByDivision'
        ));
    }
}
