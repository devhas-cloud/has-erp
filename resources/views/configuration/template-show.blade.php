@extends('layouts.app')

@section('title', 'Template '.$template->name)
@section('page-title', 'Detail Template')

@section('styles')
<style>
    .info-table td { padding: 7px 0; vertical-align: top; line-height: 1.45; }
    .info-table td:first-child {
        color: var(--text-muted);
        width: 150px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        padding-right: 12px;
    }
    .info-table td:last-child {
        font-size: 13px;
        color: var(--text-primary);
        word-break: break-word;
    }
    .info-table tr + tr td { border-top: 1px solid var(--card-border); }

    .cat-title {
        font-size: 13px; font-weight: 700; color: var(--accent);
        text-transform: uppercase; letter-spacing: .5px;
        padding: 12px 16px 8px; margin: 0;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $template->name }}</h1>
        <p class="page-header-sub">Template part instrument digunakan sebagai isian Quote Configuration</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $backUrl }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
        @if($canUpdate)
        <a href="{{ $editUrl }}" class="btn-accent">
            <i class="fa fa-pen me-1"></i> <span>Edit Template</span>
        </a>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-bookmark me-2" style="color:var(--accent)"></i>Identitas Template</span>
            </div>
            <div class="card-body-custom">
                <table class="info-table">
                    <tr>
                        <td>Nama</td>
                        <td><strong>{{ $template->name }}</strong></td>
                    </tr>
                    <tr>
                        <td>Deskripsi</td>
                        <td>{!! $template->description ? \App\Models\Quotation::renderDescription($template->description) : '<span style="color:var(--text-muted)">—</span>' !!}</td>
                    </tr>
                    <tr>
                        <td>Jumlah Item</td>
                        <td>{{ $template->items->count() }}</td>
                    </tr>
                    <tr>
                        <td>Total Qty</td>
                        <td>{{ $template->items->sum('qty') }}</td>
                    </tr>
                    <tr>
                        <td>Dibuat Oleh</td>
                        <td>{{ $template->creator?->display_name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Tanggal Dibuat</td>
                        <td>{{ $template->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>List Part Instrument</span>
            </div>
            <div class="card-body-custom p-2">
                @php
                    $items = $template->items;
                    $children = $items->groupBy(fn ($it) => $it->parent_id ?: '_root');

                    $groupMap = [];
                    $groupOrder = [];
                    foreach (($children['_root'] ?? []) as $p) {
                        $cat = $p->category ?: 'Lainnya';
                        if (! isset($groupMap[$cat])) {
                            $groupMap[$cat] = [];
                            $groupOrder[] = $cat;
                        }
                        $groupMap[$cat][] = $p;
                    }
                @endphp

                @forelse($groupOrder as $category)
                    <div class="cat-title">{{ $category }}</div>
                    <div class="table-responsive mb-4">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:70px">No</th>
                                    <th style="width:170px">Part Number</th>
                                    <th>Deskripsi</th>
                                    <th style="width:60px" class="text-center">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $catRows = [];
                                    $walk = function ($parentId, $depth) use (&$walk, &$catRows, $children) {
                                        foreach ($children[$parentId] ?? [] as $item) {
                                            $catRows[] = ['item' => $item, 'depth' => $depth];
                                            $walk($item->id, $depth + 1);
                                        }
                                    };
                                    foreach ($groupMap[$category] as $root) {
                                        $kids = $children[$root->id] ?? collect();
                                        if ($kids->isEmpty()) {
                                            // Root tanpa children -> dirender sbg baris data.
                                            $catRows[] = ['item' => $root, 'depth' => 0];
                                        } else {
                                            // Root = judul kategori; children mulai kedalaman 1.
                                            $walk($root->id, 1);
                                        }
                                    }
                                @endphp
                                @forelse($catRows as $row)
                                    @php
                                        $item = $row['item'];
                                        $depth = $row['depth'];
                                    @endphp
                                    <tr>
                                        <td class="text-center" style="padding-left:{{ 12 + $depth * 20 }}px">{{ $item->item_no }}</td>
                                        <td><code>{{ $item->part_number ?? '—' }}</code></td>
                                        <td>
                                            <div style="margin-left:{{ $depth * 20 }}px">
                                                {!! \App\Models\Quotation::renderDescription($item->description) !!}
                                            </div>
                                        </td>
                                        <td class="text-center">{{ $item->qty }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center" style="color:var(--text-muted);padding:16px">Tidak ada item.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @empty
                    <div class="text-center" style="color:var(--text-muted);padding:24px">Belum ada item.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection