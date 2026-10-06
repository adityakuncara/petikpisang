<x-filament-widgets::widget>

    {{-- ── Label Section ── --}}
    <div style="margin-bottom: 0.75rem;">
        <span style="font-size: 0.7rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.1em;">
            📈 Ringkasan Penjualan
        </span>
        <span style="font-size: 0.7rem; color: #9ca3af; margin-left: 0.5rem;">
            (3 tahun terakhir)
        </span>
    </div>

    @php $stats = $this->getStats(); @endphp

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">

        @foreach($stats as $stat)
            <div style="
                position: relative;
                background: white;
                border-radius: 14px;
                padding: 1.25rem 1.5rem 1rem;
                box-shadow: 0 1px 4px rgba(0,0,0,0.07), 0 0 0 1px #f0f0f0;
                border-left: 4px solid {{ $stat['border'] }};
                overflow: hidden;
            ">
                {{-- Header: icon + label + sub ── --}}
                <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.65rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.07em;">
                            {{ $stat['label'] }}
                        </div>
                        <div style="font-size: 0.72rem; color: {{ $stat['color'] }}; font-weight: 600; margin-top: 0.1rem;">
                            {{ $stat['sub'] }}
                        </div>
                    </div>
                    <div style="
                        display: inline-flex; align-items: center; justify-content: center;
                        width: 38px; height: 38px; border-radius: 10px;
                        background: {{ $stat['iconBg'] }};
                        font-size: 1.2rem;
                        flex-shrink: 0;
                    ">{{ $stat['icon'] }}</div>
                </div>

                {{-- Nama produk / bulan ── --}}
                <div style="font-size: 0.9rem; font-weight: 700; color: #111827; line-height: 1.3; margin-bottom: 0.5rem; min-height: 2.2rem;">
                    {{ $stat['title'] }}
                </div>

                {{-- Divider ── --}}
                <div style="height: 1px; background: #f3f4f6; margin-bottom: 0.6rem;"></div>

                {{-- Metrik utama ── --}}
                <div style="font-size: 1.4rem; font-weight: 800; color: {{ $stat['color'] }}; line-height: 1.1;">
                    {{ $stat['value'] }}
                </div>

                {{-- Metrik sekunder ── --}}
                <div style="font-size: 0.72rem; color: #6b7280; margin-top: 0.25rem;">
                    {{ $stat['revenue'] }}
                </div>

                {{-- Dekorasi sudut ── --}}
                <div style="
                    position: absolute; bottom: -20px; right: -20px;
                    width: 80px; height: 80px; border-radius: 50%;
                    background: {{ $stat['iconBg'] }};
                    opacity: 0.5;
                "></div>
            </div>
        @endforeach

    </div>

</x-filament-widgets::widget>
