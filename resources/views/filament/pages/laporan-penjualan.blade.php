<x-filament-panels::page>

    {{-- ============================================================ --}}
    {{-- 3 CARD STATISTIK — selalu tampil                            --}}
    {{-- ============================================================ --}}
    @php
        $top     = $this->getTopSellingProduct();
        $lowest  = $this->getLowestSellingProduct();
        $highest = $this->getHighestTransaction();
    @endphp

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; width: 100%;">

        {{-- Card 1: Penjualan Tertinggi --}}
        <div style="background: white; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; border-top: 4px solid #3b82f6;">
            <div style="font-size: 0.65rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.4rem;">
                🏆 Produk Terlaris
            </div>
            <div style="font-size: 0.9rem; font-weight: 400; color: #111827; margin-bottom: 0.25rem; line-height: 1.3;">
                {{ $top['name'] }}
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #3b82f6;">
                {{ $top['value'] }}
            </div>
            <div style="font-size: 0.7rem; color: #9ca3af; margin-top: 0.2rem;">unit terjual (total)</div>
        </div>

        {{-- Card 2: Penjualan Terendah --}}
        <div style="background: white; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; border-top: 4px solid #f59e0b;">
            <div style="font-size: 0.65rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.4rem;">
                📉 Penjualan Terendah
            </div>
            <div style="font-size: 0.9rem; font-weight: 700; color: #111827; margin-bottom: 0.25rem; line-height: 1.3;">
                {{ $lowest['name'] }}
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #f59e0b;">
                {{ $lowest['value'] }}
            </div>
            <div style="font-size: 0.7rem; color: #9ca3af; margin-top: 0.2rem;">unit terjual (total)</div>
        </div>

        {{-- Card 3: Transaksi Nilai Terbesar --}}
        <div style="background: white; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; border-top: 4px solid #10b981;">
            <div style="font-size: 0.65rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.4rem;">
                💰 Transaksi Terbesar
            </div>
            <div style="font-size: 0.9rem; font-weight: 700; color: #111827; margin-bottom: 0.25rem; line-height: 1.3;">
                {{ $highest['name'] }}
            </div>
            <div style="font-size: 1.2rem; font-weight: 800; color: #10b981;">
                {{ $highest['value'] }}
            </div>
            <div style="font-size: 0.7rem; color: #9ca3af; margin-top: 0.2rem;">
                {{ $highest['qty'] }} · {{ $highest['date'] }}
            </div>
        </div>

    </div>

    {{-- ============================================================ --}}
    {{-- FORM FILTER                                                  --}}
    {{-- ============================================================ --}}
    <x-filament::section>
        {{ $this->form }}

        <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
            <x-filament::button
                wire:click="applyFilter"
                icon="heroicon-m-funnel"
                color="primary">
                Terapkan Filter
            </x-filament::button>

            @if($isFiltered)
                <x-filament::button
                    wire:click="resetFilter"
                    icon="heroicon-m-x-mark"
                    color="gray">
                    Reset
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>

    {{-- ============================================================ --}}
    {{-- KONTEN SETELAH FILTER                                        --}}
    {{-- ============================================================ --}}
    @if($isFiltered)

        {{-- ── Ringkasan per Produk ── --}}
        @php $summaries = $this->getSummaryByProduct(); @endphp

        @if($summaries->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">
                    Ringkasan per Produk
                    <span style="font-size: 0.75rem; font-weight: 400; color: #6b7280; margin-left: 0.5rem;">
                        ({{ $summaries->count() }} produk)
                    </span>
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="background: #f3f4f6;">
                                <th style="padding: 0.65rem 0.75rem; text-align: left; border-bottom: 2px solid #e5e7eb; font-size: 0.7rem; text-transform: uppercase; color: #374151; letter-spacing: 0.04em;">Produk</th>
                                <th style="padding: 0.65rem 0.75rem; text-align: right; border-bottom: 2px solid #e5e7eb; font-size: 0.7rem; text-transform: uppercase; color: #374151; letter-spacing: 0.04em;">Total Transaksi</th>
                                <th style="padding: 0.65rem 0.75rem; text-align: right; border-bottom: 2px solid #e5e7eb; font-size: 0.7rem; text-transform: uppercase; color: #374151; letter-spacing: 0.04em;">Total Qty</th>
                                <th style="padding: 0.65rem 0.75rem; text-align: right; border-bottom: 2px solid #e5e7eb; font-size: 0.7rem; text-transform: uppercase; color: #374151; letter-spacing: 0.04em;">Total Omzet</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summaries as $i => $s)
                                <tr style="border-bottom: 1px solid #f3f4f6; {{ $i % 2 === 0 ? 'background:#ffffff;' : 'background:#f9fafb;' }}">
                                    <td style="padding: 0.65rem 0.75rem; font-weight: 600; color: #111827;">{{ $s['product_name'] }}</td>
                                    <td style="padding: 0.65rem 0.75rem; text-align: right; color: #6b7280;">{{ $s['total_transaksi'] }}x</td>
                                    <td style="padding: 0.65rem 0.75rem; text-align: right; color: #111827; font-weight: 600;">{{ $s['total_qty'] }}</td>
                                    <td style="padding: 0.65rem 0.75rem; text-align: right; color: #10b981; font-weight: 700;">{{ $s['total_revenue'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- ── Tombol Download Excel ── --}}
        <div style="display: flex; justify-content: flex-end;">
            <x-filament::button
                wire:click="exportToExcel"
                icon="heroicon-o-document-arrow-down"
                color="success"
                wire:loading.attr="disabled"
                wire:target="exportToExcel">
                <span wire:loading.remove wire:target="exportToExcel">
                    Download Excel
                </span>
                <span wire:loading wire:target="exportToExcel">
                    Menyiapkan file...
                </span>
            </x-filament::button>
        </div>

        {{-- ── Tabel Detail ── --}}
        <x-filament::section>
            <x-slot name="heading">Detail Penjualan per Periode</x-slot>
            {{ $this->table }}
        </x-filament::section>

    @else
        <div style="text-align: center; padding: 3rem; color: #9ca3af;">
            <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🔍</div>
            <div style="font-weight: 600; color: #374151; margin-bottom: 0.25rem;">Belum ada filter diterapkan</div>
            <div style="font-size: 0.875rem;">Pilih filter di atas lalu klik <strong>Terapkan Filter</strong> untuk menampilkan laporan.</div>
        </div>
    @endif

</x-filament-panels::page>