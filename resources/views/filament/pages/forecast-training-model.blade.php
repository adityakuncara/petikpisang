<x-filament-panels::page>

    {{-- ============================================================ --}}
    {{-- FORM — polling hanya aktif saat training berjalan            --}}
    {{-- ============================================================ --}}
    <div @if($trainingId && !$trainingComplete) wire:poll.2s="pollTrainingStatus" @endif>
        {{ $this->form }}
    </div>

    {{-- ============================================================ --}}
    {{-- MODAL PROGRESS TRAINING                                      --}}
    {{-- ============================================================ --}}
    <x-filament::modal
        id="training-progress"
        width="4xl"
        :close-by-clicking-away="false"
        :close-button="false"
    >
        <x-slot name="heading">
            🤖 Proses Training Model
        </x-slot>

        <div style="padding: 0.5rem 0;">

            {{-- ── Progress Bar ── --}}
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <span style="font-size: 0.875rem; color: #374151;">
                        {{ $statusMessage ?: 'Menunggu...' }}
                    </span>
                    <span style="font-size: 0.875rem; font-weight: 700; color: #4f46e5;">
                        {{ number_format($progress, 0) }}%
                    </span>
                </div>

                {{-- Track --}}
                <div style="width: 100%; background: #e5e7eb; border-radius: 9999px; height: 14px; overflow: hidden;">
                    <div style="
                        height: 14px;
                        border-radius: 9999px;
                        background: linear-gradient(90deg, #6366f1, #3b82f6, #06b6d4);
                        width: {{ $progress }}%;
                        transition: width 0.8s ease-out;
                        {{ $trainingComplete ? '' : 'background-size: 200% 100%; animation: shimmer 1.5s infinite;' }}
                    "></div>
                </div>

                {{-- Shimmer animation --}}
                <style>
                    @keyframes shimmer {
                        0%   { background-position: 200% center; }
                        100% { background-position: -200% center; }
                    }
                </style>
            </div>

            {{-- ── Status icon ── --}}
            @if(!$trainingComplete)
                <div style="text-align: center; padding: 1rem 0; color: #6b7280; font-size: 0.875rem;">
                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">⏳</div>
                    Mohon tunggu, proses Grid Search sedang berjalan...
                </div>
            @endif

            {{-- ── Hasil Training (tampil mendatar) ── --}}
            @if($trainingComplete && $trainingResult)
                @php
                    $failed = $trainingResult['status'] ?? '' === 'failed';
                @endphp

                <div style="border-top: 1px solid #e5e7eb; padding-top: 1.25rem; margin-top: 0.5rem;">

                    <div style="font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 1rem;">
                        ✅ Hasil Training
                    </div>

                    {{-- 4 card atas: parameter utama --}}
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 0.75rem;">

                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.65rem; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Alpha (α)</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #1e40af;">{{ $trainingResult['alpha'] }}</div>
                        </div>

                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.65rem; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Beta (β)</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #1e40af;">{{ $trainingResult['beta'] }}</div>
                        </div>

                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.65rem; color: #16a34a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Grid Search</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #15803d;">{{ $trainingResult['grid_search_total'] ?? '-' }}</div>
                        </div>

                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.65rem; color: #16a34a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Best Rank</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #15803d;">#{{ $trainingResult['best_rank'] ?? '-' }}</div>
                        </div>

                    </div>

                    {{-- 4 card bawah: metrik evaluasi --}}
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1.25rem;">

                        @php
                            $mape = (float)($trainingResult['mape'] ?? 0);
                            $mapeColor = $mape < 10 ? '#15803d' : ($mape < 20 ? '#b45309' : '#b91c1c');
                            $mapeBg    = $mape < 10 ? '#f0fdf4' : ($mape < 20 ? '#fffbeb' : '#fef2f2');
                            $mapeBorder= $mape < 10 ? '#bbf7d0' : ($mape < 20 ? '#fde68a' : '#fecaca');
                        @endphp

                        <div style="background: {{ $mapeBg }}; border: 1px solid {{ $mapeBorder }}; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.65rem; color: {{ $mapeColor }}; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">MAPE</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: {{ $mapeColor }};">
                                {{ number_format($mape, 2, ',', '.') }}%
                            </div>
                        </div>

                        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.65rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">MAE</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #111827;">
                                {{ number_format((float)($trainingResult['mae'] ?? 0), 2, ',', '.') }}
                            </div>
                        </div>

                        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.65rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">RMSE</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #111827;">
                                {{ number_format((float)($trainingResult['rmse'] ?? 0), 2, ',', '.') }}
                            </div>
                        </div>

                        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.65rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">SSE</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #111827;">
                                {{ number_format((float)($trainingResult['sse'] ?? 0), 2, ',', '.') }}
                            </div>
                        </div>

                    </div>

                    {{-- Status badge + tombol selesai --}}
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="
                            display: inline-block;
                            padding: 0.3rem 1rem;
                            border-radius: 9999px;
                            font-size: 0.8rem;
                            font-weight: 600;
                            background: #d1fae5;
                            color: #065f46;
                        ">
                            ✓ {{ ucfirst($trainingResult['status'] ?? 'completed') }}
                        </span>

                        <x-filament::button color="success" wire:click="closeProgressModal">
                            Tutup
                        </x-filament::button>
                    </div>

                </div>
            @endif

            {{-- ── Gagal ── --}}

            @if($trainingComplete && $trainingFailed)
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 1rem; margin-top: 1rem; text-align: center; color: #b91c1c;">
                    ❌ <strong>{{ $statusMessage }}</strong>
                </div>
                <div style="margin-top: 1rem; text-align: right;">
                    <x-filament::button color="danger" wire:click="closeProgressModal">
                        Tutup
                    </x-filament::button>
                </div>
            @endif

            @if($trainingComplete && isset($trainingResult['failed']) && $trainingResult['failed'])
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 1rem; margin-top: 1rem; text-align: center; color: #b91c1c;">
                    ❌ Training gagal. Silakan coba lagi atau periksa data.
                </div>
                <div style="margin-top: 1rem; text-align: right;">
                    <x-filament::button color="danger" wire:click="closeProgressModal">
                        Tutup
                    </x-filament::button>
                </div>
            @endif

        </div>
    </x-filament::modal>

</x-filament-panels::page>