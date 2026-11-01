{{-- Gráfica de barras horizontal en HTML (sin servicios externos). $items: [['label' => ..., 'value' => 0-100|null, 'tone' => success|warning|danger|secondary]] --}}
<table class="bars">
    <tbody>
    @foreach ($items as $item)
        @php $value = $item['value'] === null ? null : max(0, min(100, (float) $item['value'])); @endphp
        <tr>
            <td class="bar-label">{{ $item['label'] }}</td>
            <td><div class="bar-track">@if ($value)<div class="bar-fill {{ $item['tone'] ?? 'secondary' }}" style="width: {{ $value }}%"></div>@endif</div></td>
            <td class="bar-value text-{{ $item['tone'] ?? 'secondary' }}">{{ $value === null ? 'Sin datos' : rtrim(rtrim(number_format($value, 2), '0'), '.').'%' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<div class="legend">
    <span><i class="swatch" style="background:#16a34a"></i>Aprobado (80 % o más)</span>
    <span><i class="swatch" style="background:#d97706"></i>Apenas cumple (70 a 79 %)</span>
    <span><i class="swatch" style="background:#dc2626"></i>Reprobado (menos de 70 %)</span>
</div>
