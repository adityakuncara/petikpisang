<x-filament-panels::page>

    {{-- ============================================================ --}}
    {{-- BAGIAN TABEL DETAIL SEMUA RIWAYAT                            --}}
    {{-- ============================================================ --}}
    <x-filament::section>
        <x-slot name="heading">Detail Riwayat Training</x-slot>
        {{ $this->table }}
    </x-filament::section>

    {{-- ============================================================ --}}
    {{-- BAGIAN RINGKASAN PER PRODUK                           --}}
    {{-- ============================================================ --}}
    @php $summaries = $this->getSummaryPerProduct(); @endphp

    <x-filament::section>
        <x-slot name="heading">
            Ringkasan Training per Produk
            <span style="font-size: 0.75rem; font-weight: 400; color: #6b7280; margin-left: 0.5rem;">
                ({{ $summaries->count() }} produk)
            </span>
        </x-slot>

        @if($summaries->isEmpty())
            <div style="text-align: center; padding: 2rem; color: #9ca3af;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📭</div>
                Belum ada riwayat training.
            </div>
        @else
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">

                @foreach($summaries as $s)
                    <div style="
                        border: 1px solid {{ $s['has_active'] ? '#bbf7d0' : '#e5e7eb' }};
                        border-left: 4px solid {{ $s['has_active'] ? '#22c55e' : '#d1d5db' }};
                        border-radius: 10px;
                        padding: 1rem 1.25rem;
                        background: {{ $s['has_active'] ? '#f0fdf4' : '#f9fafb' }};
                    ">
                        {{-- Baris 1: Nama produk + badge status --}}
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <div>
                                <span style="font-size: 0.65rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">
                                    {{ $s['product_code'] }}
                                </span>
                                <div style="font-size: 0.95rem; font-weight: 700; color: #111827;">
                                    {{ $s['product_name'] }}
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                {{-- Badge granularity --}}
                                @php
                                   $warna = $s['active_granularity'] 
                                @endphp
                                @if($s['active_granularity'] == "Weekly")
                                    <span style="
                                        font-size: 0.7rem; font-weight: 600;
                                        padding: 0.15rem 0.6rem;
                                        border-radius: 9999px;
                                        background: #dbeafe; color: #ec2106;
                                    ">
                                        {{ ucfirst($s['active_granularity']) }}
                                    </span>
                                @else
                                    <span style="
                                        font-size: 0.7rem; font-weight: 600;
                                        padding: 0.15rem 0.6rem;
                                        border-radius: 9999px;
                                        background: #dbeafe; color: #0a0096;
                                    ">
                                        {{ $s['active_granularity'] }}
                                    </span>
                                @endif
                                {{-- Badge aktif/tidak --}}
                                <span style="
                                    font-size: 0.7rem; font-weight: 600;
                                    padding: 0.15rem 0.6rem;
                                    border-radius: 9999px;
                                    background: {{ $s['has_active'] ? '#dcfce7' : '#f3f4f6' }};
                                    color: {{ $s['has_active'] ? '#15803d' : '#6b7280' }};
                                ">
                                    {{ $s['has_active'] ? '✓ Model Aktif' : 'Tidak Ada Model Aktif' }}
                                </span>
                                {{-- Total training --}}
                                <span style="
                                    font-size: 0.7rem; font-weight: 600;
                                    padding: 0.15rem 0.6rem;
                                    border-radius: 9999px;
                                    background: #f3f4f6; color: #374151;
                                ">
                                    {{ $s['total_trainings'] }}x ditraining
                                </span>
                            </div>
                        </div>

                        {{-- Baris 2: Metrik mendatar --}}
                        @if($s['has_active'])
                            @php
                                $mape       = (float)($s['active_mape'] ?? 0);
                                $mapeColor  = $mape < 10 ? '#15803d' : ($mape < 20 ? '#b45309' : '#b91c1c');
                                $mapeBg     = $mape < 10 ? '#dcfce7' : ($mape < 20 ? '#fef3c7' : '#fee2e2');
                            @endphp
                            <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.6rem;">

                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem; text-align: center;">
                                    <div style="font-size: 0.6rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em;">Alpha (α)</div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: #1e40af;">{{ $s['active_alpha'] }}</div>
                                </div>

                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem; text-align: center;">
                                    <div style="font-size: 0.6rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em;">Beta (β)</div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: #1e40af;">{{ $s['active_beta'] }}</div>
                                </div>

                                <div style="background: {{ $mapeBg }}; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem; text-align: center;">
                                    <div style="font-size: 0.6rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em;">MAPE</div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: {{ $mapeColor }};">
                                        {{ number_format($mape, 2, ',', '.') }}%
                                    </div>
                                </div>

                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem; text-align: center;">
                                    <div style="font-size: 0.6rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em;">MAE</div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: #111827;">
                                        {{ number_format((float)($s['active_mae'] ?? 0), 2, ',', '.') }}
                                    </div>
                                </div>

                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem; text-align: center;">
                                    <div style="font-size: 0.6rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em;">RMSE</div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: #111827;">
                                        {{ number_format((float)($s['active_rmse'] ?? 0), 2, ',', '.') }}
                                    </div>
                                </div>

                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem; text-align: center;">
                                    <div style="font-size: 0.6rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em;">Ditraining</div>
                                    <div style="font-size: 0.75rem; font-weight: 600; color: #374151;">
                                        {{ $s['active_trained_at'] ?? '-' }}
                                    </div>
                                </div>

                            </div>
                        @else
                            <div style="font-size: 0.8rem; color: #9ca3af; font-style: italic;">
                                Belum ada model aktif. Training terakhir: {{ $s['latest_trained_at'] ?? '-' }}
                            </div>
                        @endif

                    </div>
                @endforeach

            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>