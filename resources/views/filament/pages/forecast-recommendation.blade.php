<x-filament-panels::page>

    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if($this->product_id)
        @php
            $stats  = $this->getStats();
            $isAman = $stats['is_aman'] ?? true;
        @endphp

        {{-- 3 Card --}}
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; width: 100%;">

            {{-- Card 1: Nama Produk --}}
            <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb;">
                <div style="font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem;">
                    Nama Produk
                </div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #111827;">
                    {{ $stats['name'] ?? '-' }}
                </div>
            </div>

            {{-- Card 2: Stok Saat Ini --}}
            <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb;">
                <div style="font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem;">
                    Stok Saat Ini
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: #111827;">
                   {{ $stats['stock'] ?? '-'}}
                </div>
            </div>

            {{-- Card 3: Rekomendasi --}}
            <div style="
                background: {{ $isAman ? '#f0fdf4' : '#fef2f2' }};
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border: 1px solid {{ $isAman ? '#bbf7d0' : '#fecaca' }};
            ">
                <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; color: {{ $isAman ? '#15803d' : '#b91c1c' }};">
                    Rekomendasi
                </div>
                <div style="font-size: 1.1rem; font-weight: 700; color: {{ $isAman ? '#166534' : '#991b1b' }};">
                    {{ $stats['status'] ?? '-' }}
                </div>
                {{-- Tampilkan bulan restock jika tidak aman --}}
                @if(! $isAman && $stats['restock_month'])
                    <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #b91c1c;">
                        ⚠ Perlu restock (stock + 10% safety stock) sebelum <strong>{{ $stats['restock_month'] }}</strong>
                    </div>
                @elseif($isAman)
                    <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #15803d;">
                        ✓ Stok cukup untuk semua periode forecast
                    </div>
                @endif
            </div>

        </div>

        {{-- Tabel Proyeksi --}}
        <x-filament::section heading="Detail Proyeksi Sisa Stok">
            {{ $this->table }}
        </x-filament::section>

    @endif

</x-filament-panels::page>