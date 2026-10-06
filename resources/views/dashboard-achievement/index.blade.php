@extends('layouts.app')

@section('title', 'Dashboard Achievement')
@section('page-title', 'Dashboard Achievement')

@section('styles')
    <style>
        .ach-brand-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #f1f5f9;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .ach-brand-chip:hover {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .ach-flag {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .ach-stage-card {
            padding: 16px;
        }

        .ach-stage-card .stat-label {
            margin-top: 4px;
        }
    </style>
@endsection

@section('content')
    @php use App\Models\Quotation; @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header-title">Dashboard Achievement</h1>
            <p class="page-header-sub">Total achievement quotation Closed Won &amp; Finish, serta rincian per divisi dan
                brand terjual.</p>
        </div>
    </div>

    @if ($divisions->isEmpty())
        <div class="card-custom fade-in">
            <div class="card-body-custom">
                <div class="empty-state">
                    <i class="fa fa-trophy"></i>
                    <p>Belum ada achievement (quotation status Finish dengan opportunity stage Closed Won).</p>
                </div>
            </div>
        </div>
    @else
        <div class="row g-4 fade-in">
            <!-- ── Card 1: Total Achievement ── -->
            <div class="col-lg-4">
                <div class="stat-card accent-green">
                    <div class="stat-icon green"><i class="fa fa-trophy"></i></div>
                    <div class="stat-value">Rp {{ Quotation::formatMoney($totalAchievement) }}</div>
                    <div class="stat-label"><i class="fa fa-check-double me-1"></i>Total Achievement · Closed Won &amp;
                        Finish</div>
                </div>
            </div>

            <!-- ── Card 2: Division Achievement (grafik horizontal) ── -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <span><i class="fa fa-chart-bar me-2" style="color:var(--accent)"></i>Division Achievement</span>
                        <span class="badge"
                            style="background:var(--accent-soft);color:var(--accent)">{{ $divisions->count() }}
                            Divisi</span>
                    </div>
                    <div class="card-body-custom">
                        <div style="position:relative;height:{{ max(220, $divisions->count() * 42 + 70) }}px">
                            <canvas id="divisionAchievementChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Detail per Divisi ── -->
        <div class="mt-4">
            @foreach ($divisions as $div)
                @php
                    $divBrands = $brandsByDivision->get($div->division_id, collect());
                    $prob25 = $probabilityCountsByDivision[$div->division_id][25] ?? 0;
                    $prob50 = $probabilityCountsByDivision[$div->division_id][50] ?? 0;
                    $prob70 = $probabilityCountsByDivision[$div->division_id][70] ?? 0;
                @endphp
                <div class="card-custom fade-in stagger-{{ min($loop->iteration, 4) }} mb-3">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-building me-2"
                                style="color:var(--accent)"></i>{{ $div->division_name }}</span>
                        <span class="text-end">
                            <span class="ach-flag me-2">{{ $div->quotation_count }} Quotation</span>
                            <strong style="color:var(--accent)">Rp {{ Quotation::formatMoney($div->total) }}</strong>
                        </span>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">

                            <!-- Card: Divisi Achievement -->
                            <div class="col-lg-4">
                                <div class="stat-card accent-green ach-stage-card">
                                    <div class="stat-icon green"><i class="fa fa-trophy"></i></div>
                                    <div class="stat-value" style="font-size:22px">Rp
                                        {{ Quotation::formatMoney($div->total) }}</div>
                                    <div class="stat-label">Divisi Achievement · {{ $div->quotation_count }} quotation</div>
                                </div>
                            </div>

                            <!-- Card: Brand Divisi Achievement -->
                            <div class="col-lg-8">
                                <div class="card-custom" style="height:100%">
                                    <div class="card-header-custom">
                                        <span><i class="fa fa-tags me-2" style="color:var(--accent)"></i>Brand Divisi
                                            Achievement</span>
                                    </div>
                                    <div class="card-body-custom">
                                        @if ($divBrands->isEmpty())
                                            <small style="color:var(--text-muted)">Tidak ada brand teridentifikasi pada
                                                quotation divisi ini.</small>
                                        @else
                                            <div class="table-responsive">
                                                <table class="table table-custom align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Brand</th>
                                                            <th class="text-center" style="width:120px">Jml Item</th>
                                                            <th class="text-end" style="width:200px">Nilai Terjual</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($divBrands as $b)
                                                            <tr>
                                                                <td><span class="ach-brand-chip"><i
                                                                            class="fa fa-tag me-1"></i>{{ $b->brand }}</span>
                                                                </td>
                                                                <td class="text-center">{{ $b->item_count }}</td>
                                                                <td class="text-end">Rp
                                                                    {{ Quotation::formatMoney($b->total_value) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Card: Total Opportunity Stage 1 (New) -->
                            <div class="col-md-4">
                                <div class="stat-card accent-blue ach-stage-card">
                                    <!-- header -->
                                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">

                                        <div class="stat-icon blue"><i class="fa fa-spa"></i></div>
                                        <span style="font-size:15px;font-weight:600;color:var(--text-muted)">Opportunity &
                                            Quote 25%</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                        <div class="stat-value">{{ $prob25 }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card: Total Opportunity Stage 2 (Proposal & Quote) -->
                            <div class="col-md-4">
                                <div class="stat-card accent-amber ach-stage-card">
                                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
                                        <div class="stat-icon amber"><i class="fa fa-file-invoice"></i></div>
                                        <span style="font-size:15px;font-weight:600;color:var(--text-muted)">Opportunity &
                                            Quote 50%</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                        <div class="stat-value">{{ $prob50 }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card: Total Opportunity Stage 4 (Negotiation) -->
                            <div class="col-md-4">
                                <div class="stat-card accent-red ach-stage-card">
                                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
                                        <div class="stat-icon red"><i class="fa fa-handshake"></i></div>
                                        <span style="font-size:15px;font-weight:600;color:var(--text-muted)">Opportunity &
                                            Quote 70%</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                        <div class="stat-value">{{ $prob70 }}</div>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    @endif
@endsection

@section('scripts')
    @if ($divisions->isNotEmpty())
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js">
        </script>
        <script>
            (function() {
                const labels = @json($divisions->map(fn($d) => $d->division_name ?: '-')->values());
                const totals = @json($divisions->map(fn($d) => (float) $d->total)->values());
                const maxTotal = Math.max(...totals, 0);

                const fullMoney = v => 'Rp ' + new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 2
                }).format(v);
                const shortMoney = v => {
                    const units = [
                        [1e12, 'T'],
                        [1e9, 'M'],
                        [1e6, 'Jt'],
                        [1e3, 'Rb']
                    ];
                    for (const [n, u] of units) {
                        if (Math.abs(v) >= n) return 'Rp' + +(v / n).toFixed(1) + u;
                    }
                    return 'Rp' + v;
                };
                // Label di dalam bar jika bar cukup panjang, selain itu di luar (kanan) bar
                const isInside = ctx => maxTotal > 0 && ctx.dataset.data[ctx.dataIndex] / maxTotal > 0.35;

                new Chart(document.getElementById('divisionAchievementChart'), {
                    type: 'bar',
                    plugins: [ChartDataLabels],
                    data: {
                        labels,
                        datasets: [{
                            label: 'Division Achievement Total',
                            data: totals,
                            backgroundColor: '#0176d3',
                            hoverBackgroundColor: '#015ba7',
                            barPercentage: 0.85,
                            categoryPercentage: 0.9,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                right: 16
                            }
                        },
                        scales: {
                            x: {
                                position: 'top',
                                beginAtZero: true,
                                grace: '15%',
                                ticks: {
                                    callback: v => shortMoney(v),
                                    color: '#64748b',
                                    font: {
                                        size: 11
                                    }
                                },
                                grid: {
                                    color: '#e5e7eb'
                                },
                                title: {
                                    display: true,
                                    text: 'Total Achievement',
                                    color: '#334155',
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            },
                            y: {
                                ticks: {
                                    color: '#1e293b',
                                    font: {
                                        size: 12
                                    }
                                },
                                grid: {
                                    display: false
                                },
                                title: {
                                    display: true,
                                    text: 'Divisi',
                                    color: '#64748b',
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => fullMoney(ctx.parsed.x)
                                }
                            },
                            datalabels: {
                                anchor: 'end',
                                align: ctx => isInside(ctx) ? 'start' : 'end',
                                color: ctx => isInside(ctx) ? '#ffffff' : '#1e293b',
                                font: {
                                    size: 11,
                                    weight: '600'
                                },
                                formatter: v => fullMoney(v),
                                clamp: true,
                            }
                        }
                    }
                });
            })();
        </script>
    @endif
@endsection
