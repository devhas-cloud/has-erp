<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TaskImportService
{
    private array $categoryMap = [];

    private array $userMap = [];

    private array $whatsappGroupMap = [];

    private array $validStatuses = ['todo', 'in_progress', 'waiting_approval', 'done'];

    private array $validAlertTypes = ['none', 'email', 'whatsapp', 'both'];

    private array $validAlertTargets = ['personal', 'group', 'both'];

    public function import(string $filePath): array
    {
        $this->loadReferenceMaps();

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => ['File tidak memiliki data.'],
                'created' => 0,
            ];
        }

        $headerRow = array_shift($rows);
        $headerRow = array_map('trim', $headerRow);

        $colMap = $this->mapColumns($headerRow);

        $imported = 0;
        $errors = [];

        foreach ($rows as $rowIdx => $row) {
            $rowData = $this->extractRow($row, $colMap, $rowIdx);

            if (empty(array_filter($rowData['raw']))) {
                continue;
            }

            $validation = $this->validateRow($rowData, $rowIdx);
            if (! empty($validation)) {
                $errors = array_merge($errors, $validation);

                continue;
            }

            try {
                DB::beginTransaction();

                $task = Task::create([
                    'creator_id' => Auth::id(),
                    'title' => $rowData['title'],
                    'description' => $rowData['description'],
                    'category_id' => $rowData['category_id'],
                    'whatsapp_group_id' => $rowData['whatsapp_group_id'],
                    'due_date' => $rowData['due_date'],
                    'time' => $rowData['time'],
                    'status' => $rowData['status'],
                    'alert_type' => $rowData['alert_type'],
                    'alert_target' => $rowData['alert_target'],
                    'alert_time' => $rowData['alert_time'],
                    'requires_approval' => ! empty($rowData['assignee_ids']),
                ]);

                $assigneeIds = $rowData['assignee_ids'];
                if (empty($assigneeIds)) {
                    $assigneeIds = [Auth::id()];
                }
                $task->assignees()->sync($assigneeIds);

                DB::commit();
                $imported++;
            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = 'Baris '.($rowIdx + 2).': '.$e->getMessage();
            }
        }

        return [
            'success' => $imported,
            'failed' => count($errors),
            'errors' => $errors,
            'created' => $imported,
        ];
    }

    public static function getReferenceData(): array
    {
        $categories = TaskCategory::pluck('name')->toArray();
        $users = User::with('hierarchyRole')->get()->map(fn ($u) => $u->username.($u->hierarchyRole ? ' ('.$u->hierarchyRole->role_name.')' : ''))->toArray();
        $whatsappGroups = WhatsAppGroup::with('division')->where('status', 'Active')->get()
            ->map(fn ($g) => $g->group_name.' ('.$g->division?->division_name.')')->toArray();

        return [
            'Category' => $categories,
            'Assignee Usernames' => $users,
            'WhatsApp Group' => $whatsappGroups,
            'Status' => ['todo', 'in_progress', 'waiting_approval', 'done'],
            'Alert Type' => ['none', 'email', 'whatsapp', 'both'],
            'Alert Target' => ['personal', 'group', 'both'],
        ];
    }

    private function loadReferenceMaps(): void
    {
        TaskCategory::all()->each(function ($cat) {
            $this->categoryMap[strtolower(trim($cat->name))] = $cat->id;
        });

        User::all()->each(function ($user) {
            $this->userMap[strtolower(trim($user->username))] = $user->id;
        });

        WhatsAppGroup::where('status', 'Active')->get()->each(function ($g) {
            $this->whatsappGroupMap[strtolower(trim($g->group_name))] = $g->id;
        });
    }

    private function mapColumns(array $headerRow): array
    {
        $map = [];
        $lowerHeaders = array_map(fn ($h) => strtolower(trim($h)), $headerRow);

        $fieldMapping = [
            'title' => ['title', 'judul'],
            'description' => ['description', 'deskripsi'],
            'category' => ['category', 'kategori'],
            'assignee usernames' => ['assignee usernames', 'assignee', 'assignees', 'penanggung jawab'],
            'whatsapp group' => ['whatsapp group', 'whatsapp_group', 'whatsapp_group_name', 'wa group'],
            'due date' => ['due date', 'due_date', 'tanggal selesai', 'deadline'],
            'time' => ['time', 'waktu', 'jam'],
            'status' => ['status'],
            'alert type' => ['alert type', 'alert_type', 'tipe notifikasi'],
            'alert target' => ['alert target', 'alert_target', 'target notifikasi'],
            'alert time' => ['alert time', 'alert_time', 'waktu notifikasi'],
        ];

        foreach ($fieldMapping as $key => $aliases) {
            foreach ($lowerHeaders as $idx => $lh) {
                if (in_array($lh, $aliases)) {
                    $map[$key] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    private function extractRow(array $row, array $colMap, int $rowIdx): array
    {
        $get = fn (string $key) => isset($colMap[$key], $row[$colMap[$key]])
            ? trim((string) $row[$colMap[$key]])
            : '';

        $dueDate = $get('due date');
        $time = $get('time');
        $alertTime = $get('alert time');

        $assigneeStr = $get('assignee usernames');
        $assigneeIds = [];
        if ($assigneeStr !== '') {
            $usernames = array_map('trim', explode(',', $assigneeStr));
            foreach ($usernames as $uname) {
                $key = strtolower($uname);
                if (isset($this->userMap[$key])) {
                    $assigneeIds[] = $this->userMap[$key];
                }
            }
        }

        $whatsappGroupStr = $get('whatsapp group');
        $whatsappGroupId = null;
        if ($whatsappGroupStr !== '') {
            $wgKey = strtolower($whatsappGroupStr);
            $whatsappGroupId = $this->whatsappGroupMap[$wgKey] ?? null;
        }

        $categoryStr = $get('category');
        $categoryId = null;
        if ($categoryStr !== '') {
            $catKey = strtolower($categoryStr);
            $categoryId = $this->categoryMap[$catKey] ?? null;
        }

        $status = $get('status') ?: 'todo';

        return [
            'raw' => $row,
            'title' => $get('title'),
            'description' => $get('description'),
            'category_id' => $categoryId,
            'category_name' => $categoryStr,
            'assignee_ids' => $assigneeIds,
            'assignee_str' => $assigneeStr,
            'whatsapp_group_id' => $whatsappGroupId,
            'whatsapp_group_name' => $whatsappGroupStr,
            'due_date' => $dueDate ?: now()->format('Y-m-d'),
            'time' => $time ?: null,
            'status' => in_array($status, $this->validStatuses) ? $status : 'todo',
            'alert_type' => in_array($get('alert type'), $this->validAlertTypes) ? $get('alert type') : 'none',
            'alert_target' => in_array($get('alert target'), $this->validAlertTargets) ? $get('alert target') : 'personal',
            'alert_time' => $alertTime ?: null,
        ];
    }

    private function validateRow(array $rowData, int $rowIdx): array
    {
        $errors = [];
        $line = $rowIdx + 2;

        if (empty($rowData['title'])) {
            $errors[] = 'Baris '.$line.': Title wajib diisi.';
        }

        if (empty($rowData['category_id'])) {
            $errCat = 'Baris '.$line.': Category "'.$rowData['category_name'].'" tidak ditemukan. Cek Daftar Referensi.';
            $errors[] = $errCat;
        }

        if (! empty($rowData['due_date'])) {
            try {
                $d = new \DateTime($rowData['due_date']);
            } catch (\Exception $e) {
                $errors[] = 'Baris '.$line.': Due Date format tidak valid ("'.$rowData['due_date'].'"). Gunakan format YYYY-MM-DD HH:MM.';
            }
        }

        if (! empty($rowData['assignee_str']) && empty($rowData['assignee_ids'])) {
            $errors[] = 'Baris '.$line.': Assignee username "'.$rowData['assignee_str'].'" tidak ditemukan. Cek Daftar Referensi.';
        }

        if (! empty($rowData['whatsapp_group_name']) && empty($rowData['whatsapp_group_id'])) {
            $errors[] = 'Baris '.$line.': WhatsApp Group "'.$rowData['whatsapp_group_name'].'" tidak ditemukan. Cek Daftar Referensi.';
        }

        if (! empty($rowData['alert_time'])) {
            try {
                $d = new \DateTime($rowData['alert_time']);
            } catch (\Exception $e) {
                $errors[] = 'Baris '.$line.': Alert Time format tidak valid ("'.$rowData['alert_time'].'").';
            }
        }

        return $errors;
    }
}
