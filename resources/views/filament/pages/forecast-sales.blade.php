<x-filament-panels::page>

    {{-- ============================================================ --}}
    {{-- FORM FILTER                                                  --}}
    {{-- ============================================================ --}}
    {{ $this->form }}
    <div class="mt-4" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <x-filament::button color="primary" wire:click="generate">
            📊 Generate Forecast
        </x-filament::button>

        @if(!empty($forecast))
            <x-filament::button color="success" wire:click="export"
                wire:loading.attr="disabled" wire:target="export">
                <span wire:loading.remove wire:target="export">🧾 Export Excel</span>
                <span wire:loading wire:target="export">Menyiapkan...</span>
            </x-filament::button>

            <x-filament::button color="info" wire:click="openChart">
                📈 Lihat Grafik
            </x-filament::button>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- RINGKASAN MODEL                                              --}}
    {{-- ============================================================ --}}
    @if($summary)
        <x-filament::section>
            <x-slot name="heading">Ringkasan Model — {{ $productName ?? 'Produk' }}</x-slot>

            {{-- Info rentang data yang digunakan --}}
            @if($dataRangeInfo)
                <div style="margin-bottom: 1rem; padding: 0.5rem 0.85rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; font-size: 0.8rem; color: #1e40af;">
                    📅 Data yang digunakan: <strong>{{ $dataRangeInfo }}</strong>
                </div>
            @endif

            <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem;">
                @foreach([
                    ['Alpha (α)', number_format($summary['alpha'], 2, ',', '.')],
                    ['Beta (β)',  number_format($summary['beta'],  2, ',', '.')],
                    ['MAE',      number_format($summary['mae'],   2, ',', '.')],
                    ['MAPE',     number_format($summary['mape'],  2, ',', '.') . '%'],
                    ['RMSE',     number_format($summary['rmse'],  2, ',', '.')],
                    ['SSE',      number_format($summary['sse'],   2, ',', '.')],
                ] as [$label, $value])
                    @php
                        $mapeColor = ($label === 'MAPE' && (float)$summary['mape'] >= 20) ? '#b91c1c' : '#111827';
                    @endphp
                    <div style="text-align:center; padding:1rem; background:#f9fafb; border-radius:8px; border:1px solid #e5e7eb;">
                        <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">{{ $label }}</div>
                        <div style="font-size:1.25rem; font-weight:700; color:{{ $mapeColor }};">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- ============================================================ --}}
    {{-- FUTURE FORECAST                                              --}}
    {{-- ============================================================ --}}
    @if($futureForecast)
        @php $isWeekly = ($data['granularity'] ?? '') === \App\Enums\GranularityEnum::WEEKLY->value; @endphp
        <x-filament::section>
            <x-slot name="heading">Future Forecast — {{ $productName ?? 'Produk' }}</x-slot>

            <div style="overflow-x: auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                    <thead>
                        <tr style="background:#f3f4f6;">
                            @foreach(['No','Periode','Prediksi (qty)','Level','Trend','Interval Prediksi','Status'] as $th)
                                <th style="padding:0.65rem 0.75rem; text-align:{{ in_array($th,['No','Periode','Status','Interval Prediksi']) ? 'left' : 'right' }}; border-bottom:2px solid #e5e7eb; color:#374151; font-weight:600; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; white-space:nowrap;">
                                    {{ $th }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($futureForecast as $i => $row)
                            @php
                                $trend = (float)($row['trend'] ?? 0);
                                if ($trend > 50) {
                                    [$statusLabel,$statusColor,$statusBg,$statusIcon] = ['Mengalami Kenaikan','#15803d','#dcfce7','↑'];
                                } elseif ($trend < -50) {
                                    [$statusLabel,$statusColor,$statusBg,$statusIcon] = ['Mengalami Penurunan','#b91c1c','#fee2e2','↓'];
                                } else {
                                    [$statusLabel,$statusColor,$statusBg,$statusIcon] = ['Kondisi Aman','#1e40af','#dbeafe','→'];
                                }
                                $lower = isset($row['interval_lower']) ? number_format((float)$row['interval_lower'],0,',','.') : '-';
                                $upper = isset($row['interval_upper']) ? number_format((float)$row['interval_upper'],0,',','.') : '-';

                                // Label periode
                                $pd = \Carbon\Carbon::parse($row['period_date']);
                                if ($isWeekly) {
                                    $periodeLabel = $pd->translatedFormat('My') . '-MG' . str_pad($pd->isoWeek(), 2, '0', STR_PAD_LEFT);
                                } else {
                                    $periodeLabel = $pd->translatedFormat('F Y');
                                }
                            @endphp
                            <tr style="border-bottom:1px solid #f3f4f6; {{ $i%2===0 ? 'background:#fff;' : 'background:#f9fafb;' }}">
                                <td style="padding:0.65rem 0.75rem; color:#6b7280;">{{ $i+1 }}</td>
                                <td style="padding:0.65rem 0.75rem; color:#111827; font-weight:500; white-space:nowrap;">{{ $periodeLabel }}</td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:#111827; font-weight:700;">
                                    {{ $row['forecast'] !== null ? number_format((float)$row['forecast'],0,',','.') : '-' }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:#6b7280;">
                                    {{ $row['level'] !== null ? number_format((float)$row['level'],2,',','.') : '-' }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:{{ $trend>=0?'#15803d':'#b91c1c' }}; font-weight:600;">
                                    {{ $row['trend'] !== null ? number_format($trend,2,',','.') : '-' }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; color:#ca007d; white-space:nowrap; font-weight:600;">{{ $lower }} – {{ $upper }}</td>
                                <td style="padding:0.65rem 0.75rem;">
                                    <span style="display:inline-block; padding:0.2rem 0.65rem; border-radius:9999px; font-size:0.75rem; font-weight:600; background:{{ $statusBg }}; color:{{ $statusColor }};">
                                        {{ $statusIcon }} {{ $statusLabel }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- ============================================================ --}}
    {{-- TABEL DETAIL AKTUAL VS FORECAST                             --}}
    {{-- ============================================================ --}}
    @if($trainingForecast)
        @php
            $isWeekly      = ($data['granularity'] ?? '') === \App\Enums\GranularityEnum::WEEKLY->value;
            $filteredData  = $this->getFilteredTrainingForecast();
            $totalFiltered = count($filteredData);
            $totalAll      = count($trainingForecast);
        @endphp

        <x-filament::section>

            {{-- Header + Filter ──────────────────────────────────── --}}
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem;">
                <div>
                    <span style="font-weight:600; font-size:0.95rem; color:#111827;">
                        Detail Perhitungan Aktual vs Forecast — {{ $productName ?? 'Produk' }}
                    </span>
                    <span style="font-size:0.75rem; color:#6b7280; margin-left:0.5rem;">
                        @if($isWeekly)
                            (24 bulan terakhir · {{ $totalFiltered }} dari {{ $totalAll }} data)
                        @elseif($filterYear)
                            (Tahun {{ $filterYear }} · {{ $totalFiltered }} dari {{ $totalAll }} data)
                        @else
                            ({{ $totalAll }} data)
                        @endif
                    </span>
                </div>

                {{-- Dropdown tahun — hanya Monthly --}}
                @if(! $isWeekly && count($this->getAvailableYears()) > 1)
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:0.8rem; color:#6b7280;">Tahun:</span>
                        <select wire:model.live="filterYear"
                            style="font-size:0.8rem; padding:0.35rem 0.65rem; border:1px solid #d1d5db; border-radius:6px; background:white; color:#374151; cursor:pointer;">
                            <option value="">Semua</option>
                            @foreach($this->getAvailableYears() as $yr => $lbl)
                                <option value="{{ $yr }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                        @if($filterYear)
                            <button wire:click="$set('filterYear', null)"
                                style="font-size:0.75rem; padding:0.3rem 0.6rem; border:1px solid #e5e7eb; border-radius:6px; background:#f9fafb; color:#6b7280; cursor:pointer;">
                                ✕ Reset
                            </button>
                        @endif
                    </div>
                @endif

                {{-- Info weekly: keterangan periode-0 --}}
                @if($isWeekly)
                    <div style="font-size:0.72rem; color:#6b7280; font-style:italic;">
                        * Baris pertama = periode basis inisialisasi L₀/T₀ DES
                    </div>
                @endif
            </div>

            {{-- Tabel ──────────────────────────────────────────────── --}}
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                    <thead>
                        <tr style="background:#f3f4f6;">
                            @foreach(['No','Periode','Aktual','Forecast','Level','Trend','Error','|Error|','APE (%)'] as $th)
                                <th style="padding:0.65rem 0.75rem; text-align:{{ in_array($th,['No','Periode']) ? 'left':'right' }}; border-bottom:2px solid #e5e7eb; color:#374151; font-weight:600; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; white-space:nowrap;">
                                    {{ $th }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->getTrainingForecastPaginated() as $i => $row)
                            @php
                                $no  = (($trainingPage - 1) * $trainingPerPage) + $i + 1;
                                $pd  = \Carbon\Carbon::parse($row['period_date']);
                                $periodeLabel = $isWeekly
                                    ? $pd->translatedFormat('My') . '-MG' . str_pad($pd->isoWeek(), 2, '0', STR_PAD_LEFT)
                                    : $pd->translatedFormat('M Y');

                                // Tandai baris pertama sebagai basis L0/T0
                                $isBasis = $isWeekly && ($row['index'] === 1);
                            @endphp
                            <tr style="border-bottom:1px solid #f3f4f6; {{ $i%2===0 ? 'background:#fff;' : 'background:#f9fafb;' }} {{ $isBasis ? 'border-left:3px solid #6366f1;' : '' }}">
                                <td style="padding:0.65rem 0.75rem; color:#6b7280;">
                                    {{ $no }}
                                    @if($isBasis)
                                        <span style="font-size:0.6rem; color:#6366f1; font-weight:700; margin-left:2px;">L₀</span>
                                    @endif
                                </td>
                                <td style="padding:0.65rem 0.75rem; color:#111827; font-weight:500; white-space:nowrap;">
                                    {{ $periodeLabel }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:#111827;">
                                    {{ number_format((float)$row['actual'],0,',','.') }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:#111827;">
                                    {{ $row['forecast'] !== null ? number_format((float)$row['forecast'],0,',','.') : '-' }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:#6b7280;">
                                    {{ number_format((float)$row['level'],2,',','.') }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:#6b7280;">
                                    {{ number_format((float)$row['trend'],2,',','.') }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:{{ $row['error'] !== null && (float)$row['error'] < 0 ? '#b91c1c' : '#111827' }};">
                                    {{ $row['error'] !== null ? number_format((float)$row['error'],2,',','.') : '-' }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right; color:#111827;">
                                    {{ $row['absolute_error'] !== null ? number_format((float)$row['absolute_error'],2,',','.') : '-' }}
                                </td>
                                <td style="padding:0.65rem 0.75rem; text-align:right;">
                                    @if($row['ape'] !== null)
                                        <span style="color:{{ (float)$row['ape'] < 20 ? '#15803d' : '#b91c1c' }}; font-weight:600;">
                                            {{ number_format((float)$row['ape'],2,',','.') }}%
                                        </span>
                                    @else
                                        <span style="color:#9ca3af;">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="padding:2rem; text-align:center; color:#9ca3af;">
                                    Tidak ada data untuk filter yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginasi ──────────────────────────────────────────── --}}
            @php
                $totalPages  = $this->getTrainingTotalPages();
                $currentPage = $trainingPage;
                $from        = (($currentPage - 1) * $trainingPerPage) + 1;
                $to          = min($currentPage * $trainingPerPage, $totalFiltered);
            @endphp

            @if($totalPages > 1)
                <div style="display:flex; align-items:center; justify-content:space-between; margin-top:1rem; padding-top:1rem; border-top:1px solid #e5e7eb;">
                    <div style="font-size:0.8rem; color:#6b7280;">
                        Menampilkan <strong>{{ $from }}</strong>–<strong>{{ $to }}</strong> dari <strong>{{ $totalFiltered }}</strong> data
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <button wire:click="trainingPreviousPage" {{ $currentPage <= 1 ? 'disabled' : '' }}
                            style="padding:0.4rem 0.85rem; border-radius:6px; border:1px solid #e5e7eb; background:{{ $currentPage<=1?'#f9fafb':'#fff' }}; color:{{ $currentPage<=1?'#9ca3af':'#374151' }}; font-size:0.8rem; cursor:{{ $currentPage<=1?'not-allowed':'pointer' }};">
                            ← Prev
                        </button>

                        @for($p = 1; $p <= $totalPages; $p++)
                            @if($p == 1 || $p == $totalPages || abs($p - $currentPage) <= 1)
                                <button wire:click="$set('trainingPage', {{ $p }})"
                                    style="padding:0.4rem 0.7rem; border-radius:6px; border:1px solid {{ $p===$currentPage?'#6366f1':'#e5e7eb' }}; background:{{ $p===$currentPage?'#6366f1':'#fff' }}; color:{{ $p===$currentPage?'#fff':'#374151' }}; font-size:0.8rem; font-weight:{{ $p===$currentPage?'700':'400' }}; cursor:pointer;">
                                    {{ $p }}
                                </button>
                            @elseif(abs($p - $currentPage) == 2)
                                <span style="color:#9ca3af; font-size:0.8rem;">...</span>
                            @endif
                        @endfor

                        <button wire:click="trainingNextPage" {{ $currentPage >= $totalPages ? 'disabled' : '' }}
                            style="padding:0.4rem 0.85rem; border-radius:6px; border:1px solid #e5e7eb; background:{{ $currentPage>=$totalPages?'#f9fafb':'#fff' }}; color:{{ $currentPage>=$totalPages?'#9ca3af':'#374151' }}; font-size:0.8rem; cursor:{{ $currentPage>=$totalPages?'not-allowed':'pointer' }};">
                            Next →
                        </button>
                    </div>
                </div>
            @endif

        </x-filament::section>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL CHART                                                  --}}
    {{-- ============================================================ --}}
    <x-filament::modal id="forecast-chart" width="full" class="max-w-5xl mx-auto">
        <x-slot name="heading">
            Grafik Actual vs Forecast — {{ $productName ?? 'Produk' }}
        </x-slot>

        <div style="height:420px;">
            <canvas id="forecastChart"></canvas>
        </div>

        <div class="mt-4 flex gap-2">
            <x-filament::button color="success" onclick="downloadForecastChart()">
                📷 Unduh Grafik
            </x-filament::button>
            <x-filament::button color="gray" wire:click="closeChart">
                Tutup
            </x-filament::button>
        </div>
    </x-filament::modal>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let forecastChart = null;

        function initForecastChart(data) {
            const ctx = document.getElementById('forecastChart');
            if (!ctx) return;
            if (forecastChart) { forecastChart.destroy(); forecastChart = null; }

            // Validasi: pastikan data tidak kosong
            if (!data || !data.labels || data.labels.length === 0) {
                console.warn('Chart: data kosong.');
                return;
            }

            const datasets = [];

            // 1. Aktual
            datasets.push({
                label: 'Aktual',
                data: data.actual,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,0.1)',
                pointRadius: 4,
                pointBackgroundColor: '#2563eb',
                tension: 0.3,
                fill: false,
                spanGaps: false,
            });

            // 2. Forecast (in-sample + future dalam satu dataset agar garis nyambung)
            datasets.push({
                label: 'Prakiraan',
                data: data.forecast,
                borderColor: '#f59e0b',
                borderDash: [6, 6],
                pointRadius: 4,
                pointBackgroundColor: '#f59e0b',
                tension: 0.3,
                fill: false,
                spanGaps: true,   // ← agar null di index-0 tidak memutus garis
            });

            // 3. Level — default hidden
            datasets.push({
                label: 'Level (ℓₜ)',
                data: data.level,
                borderColor: '#dc2626',
                borderDash: [10, 5],
                pointRadius: 0,
                fill: false,
                hidden: true,
                spanGaps: true,
            });

            // 4. APE — sumbu Y kanan, default hidden
            const hasApe = data.ape && data.ape.some(v => v !== null);
            if (hasApe) {
                datasets.push({
                    label: 'APE (%)',
                    data: data.ape,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,0.1)',
                    pointRadius: 3,
                    fill: false,
                    yAxisID: 'y1',
                    hidden: true,
                    spanGaps: false,
                });
            }

            // 5. Interval prediksi
            const hasInterval = data.upper && data.upper.some(v => v !== null);
            if (hasInterval) {
                datasets.push({
                    label: 'Interval Atas',
                    data: data.upper,
                    borderColor: 'rgba(245,158,11,0.5)',
                    borderDash: [4, 4],
                    pointRadius: 0,
                    fill: false,
                    spanGaps: true,
                });
                datasets.push({
                    label: 'Interval Bawah',
                    data: data.lower,
                    borderColor: 'rgba(245,158,11,0.5)',
                    borderDash: [4, 4],
                    pointRadius: 0,
                    fill: '-1',
                    backgroundColor: 'rgba(245,158,11,0.12)',
                    spanGaps: true,
                });
            }

            const scales = {
                x: { ticks: { color: '#6b7280', maxRotation: 45 } },
                y: { ticks: { color: '#6b7280' } },
            };

            if (hasApe) {
                scales.y1 = {
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#6b7280', callback: v => v + '%' },
                };
            }

            forecastChart = new Chart(ctx, {
                type: 'line',
                data: { labels: data.labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const v = ctx.parsed.y;
                                    if (v === null || isNaN(v)) return null;
                                    return ctx.dataset.label + ': ' +
                                        (ctx.dataset.label === 'APE (%)'
                                            ? v.toFixed(2) + '%'
                                            : new Intl.NumberFormat('id-ID').format(Math.round(v)));
                                }
                            }
                        },
                        legend: { labels: { color: '#374151' } },
                    },
                    scales,
                },
            });
        }

        function downloadForecastChart() {
            if (forecastChart) {
                const link = document.createElement('a');
                link.download = 'forecast-chart.png';
                link.href = forecastChart.toBase64Image();
                link.click();
            } else {
                alert('Chart belum digambar.');
            }
        }

        document.addEventListener('livewire:initialized', () => {
            Livewire.on('chart-data', (event) => {
                setTimeout(() => initForecastChart(event.data), 350);
            });
        });
    </script>
    @endpush

</x-filament-panels::page>