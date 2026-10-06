<x-filament-widgets::widget>

    {{-- ── Label Section ── --}}
    <div style="margin-bottom: 0.75rem;">
        <span style="font-size: 0.7rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.1em;">
            📊 Statistik Produk
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
                overflow: hidden;
            ">
                {{-- Garis atas berwarna --}}
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: {{ $stat['color'] }};"></div>

                {{-- Icon --}}
                <div style="
                    display: inline-flex; align-items: center; justify-content: center;
                    width: 44px; height: 44px; border-radius: 12px;
                    background: {{ $stat['iconBg'] }};
                    font-size: 1.4rem;
                    margin-bottom: 0.85rem;
                ">{{ $stat['icon'] }}</div>

                {{-- Label --}}
                <div style="font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 0.25rem;">
                    {{ $stat['label'] }}
                </div>

                {{-- Value --}}
                <div style="font-size: 2rem; font-weight: 800; color: {{ $stat['color'] }}; line-height: 1.1; margin-bottom: 0.3rem;">
                    {{ $stat['value'] }}
                </div>

                {{-- Sub --}}
                <div style="font-size: 0.72rem; color: #6b7280;">
                    {{ $stat['sub'] }}
                </div>

                {{-- Dekorasi sudut --}}
                <div style="
                    position: absolute; bottom: -18px; right: -18px;
                    width: 70px; height: 70px; border-radius: 50%;
                    background: {{ $stat['iconBg'] }};
                    opacity: 0.6;
                "></div>
            </div>
        @endforeach

    </div>

</x-filament-widgets::widget>
