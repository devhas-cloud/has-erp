<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\ProjectAchievement;

/**
 * Dashboard Achievement: nilai achievement dihitung dari rincian device project
 * (project_achievements.amount) per divisi device, bukan dari quotation.
 * Project berstatus Cancelled tidak dihitung.
 */
class DashboardAchievementController extends Controller
{
    /** Baris divisi tetap; divisi tanpa data tetap tampil dengan nilai 0. */
    private const DIVISION_ORDER = ['Enviro', 'Water', 'IH', 'Gas', 'IMS'];

    public function index()
    {
        // 1. Total achievement (semua divisi)
        $totalAchievement = (float) $this->achievementQuery()->sum('project_achievements.amount');

        // 2. Achievement per divisi (project_achievements.division_id).
        $divisionList = Division::whereIn('division_name', self::DIVISION_ORDER)->where('type', 'Internal')
            ->get(['id', 'division_name'])
            ->unique('division_name');

        // Urutkan sesuai urutan daftar (di luar DB agar portabel lintas driver).
        $divisionRank = array_flip(self::DIVISION_ORDER);
        $divisionList = $divisionList
            ->sortBy(fn ($d) => $divisionRank[$d->division_name] ?? 99)
            ->values();

        $achievementRows = $this->achievementQuery()
            ->groupBy('project_achievements.division_id')
            ->selectRaw(
                'project_achievements.division_id,
                 COUNT(DISTINCT project_achievements.project_id) as project_count,
                 SUM(project_achievements.amount) as total'
            )
            ->get()
            ->keyBy('division_id');

        $divisions = $divisionList
            ->map(fn ($d) => (object) [
                'division_id' => $d->id,
                'division_name' => $d->division_name,
                'project_count' => (int) ($achievementRows[$d->id]->project_count ?? 0),
                'total' => (float) ($achievementRows[$d->id]->total ?? 0),
            ])
            ->values();

        // 3. Brand terjual per divisi dari rincian device project. Device tanpa brand
        //    dikelompokkan "Tanpa Brand" agar total brand = total divisi.
        $brandsByDivision = $this->achievementQuery()
            ->whereIn('project_achievements.division_id', $divisionList->pluck('id'))
            ->groupBy('project_achievements.division_id', 'project_achievements.brand')
            ->selectRaw(
                'project_achievements.division_id,
                 project_achievements.brand,
                 COUNT(*) as item_count,
                 SUM(project_achievements.qty) as qty_total,
                 SUM(project_achievements.amount) as total_value'
            )
            ->get()
            ->groupBy('division_id')
            ->map(fn ($rows) => $rows
                // Kosong & NULL digabung jadi satu baris "Tanpa Brand".
                ->groupBy(fn ($r) => trim((string) $r->brand) === '' ? '' : $r->brand)
                ->map(fn ($group, $brand) => (object) [
                    'brand' => $brand === '' ? null : $brand,
                    'item_count' => (int) $group->sum('item_count'),
                    'qty_total' => (float) $group->sum('qty_total'),
                    'total_value' => (float) $group->sum('total_value'),
                ])
                ->sortBy(fn ($b) => [$b->brand === null ? 1 : 0, -$b->total_value])
                ->values());

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

    /** Rincian achievement project yang tidak dibatalkan. */
    private function achievementQuery()
    {
        return ProjectAchievement::query()
            ->join('projects', 'projects.id', '=', 'project_achievements.project_id')
            ->where('projects.status', '!=', Project::STATUS_CANCELLED);
    }
}
