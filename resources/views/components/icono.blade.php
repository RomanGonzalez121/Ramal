@props(['nombre', 'tamano' => 24])
<svg {{ $attributes->merge(['class' => 'icono shrink-0']) }} width="{{ $tamano }}" height="{{ $tamano }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false"><use href="#i-{{ $nombre }}"/></svg>
