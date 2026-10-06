<x-filament-widgets::widget>
    @php $summaries = $this->getPaginatedSummaries(); @endphp
    
    <x-filament::section>
        <x-slot name="heading">Ringkasan Training per Produk</x-slot>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @foreach($summaries as $s)
                <div style="
                    border: 1px solid {{ $s['has_active'] ? '#bbf7d0' : '#e5e7eb' }};
                    border-left: 4px solid {{ $s['has_active'] ? '#22c55e' : '#d1d5db' }};
                    border-radius: 10px;
                    padding: 1rem 1.25rem;
                    background: {{ $s['has_active'] ? '#f0fdf4' : '#f9fafb' }};
                ">
                    {{-- Judul Produk --}}
                    <div style="margin-bottom: 0.75rem; font-weight: 700; color: #111827;">
                        {{ $s['product_name'] }}
                        <x-filament::badge
                            :color="$s['granularity'] === 'Monthly'
                                ? 'info'
                                : 'warning'">

                            {{ $s['granularity'] }}

                        </x-filament::badge>
                    </div>

                    @if($s['has_active'])
                        <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.6rem;">
                            @foreach(['Alpha (α)' => $s['active_alpha'], 'Beta (β)' => $s['active_beta'], 'MAPE' => $s['active_mape'].'%', 'MAE' => $s['active_mae'], 'RMSE' => $s['active_rmse']] as $label => $val)
                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem; text-align: center;">
                                    <div style="font-size: 0.6rem; color: #9ca3af; text-transform: uppercase;">{{ $label }}</div>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: #111827;">{{ number_format((float)$val, 2) }}</div>
                                </div>
                            @endforeach
                            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem; text-align: center;">
                                <div style="font-size: 0.6rem; color: #9ca3af; text-transform: uppercase;">Trained At</div>
                                <div style="font-size: 0.8rem; font-weight: 600;">{{ $s['active_trained_at'] }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2.5rem !important; border-top: 1px solid #e5e7eb; padding-top: 1.5rem;">
            @for($i = 1; $i <= $summaries->lastPage(); $i++)
                <x-filament::button size="sm" 
                    :color="$i === $this->page ? 'primary' : 'gray'" 
                    wire:click="setPage({{ $i }})">
                    {{ $i }}
                </x-filament::button>
            @endfor
        </div>
    </x-filament::section>
</x-filament-widgets::widget>