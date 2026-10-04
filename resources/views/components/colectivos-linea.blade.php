{{-- Decoración: tres colectivos de perfil dibujados con el trazo del sistema (2 px, terminaciones cuadradas). --}}
<svg {{ $attributes->merge(['class' => 'w-full']) }} viewBox="0 0 640 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMax meet">
    <path d="M0 58h640" stroke-dasharray="14 12" opacity=".6"/>
    {{-- grande --}}
    <g transform="translate(18 10)">
        <path d="M0 6h150v40H0zM0 20h150M14 6v14M42 6v14M70 6v14M98 6v14M126 6v14M150 20h-26v26"/>
        <circle cx="30" cy="48" r="5"/><circle cx="118" cy="48" r="5"/>
    </g>
    {{-- articulado --}}
    <g transform="translate(206 10)">
        <path d="M0 6h96v40H0zM104 6h96v40h-96zM96 14h8v26h-8zM0 20h200M12 6v14M36 6v14M60 6v14M84 6v14M116 6v14M140 6v14M164 6v14M188 6v14"/>
        <circle cx="28" cy="48" r="5"/><circle cx="76" cy="48" r="5"/><circle cx="150" cy="48" r="5"/><circle cx="178" cy="48" r="5"/>
    </g>
    {{-- chico --}}
    <g transform="translate(436 20)">
        <path d="M0 6h110v30H0zM0 16h110M14 6v10M42 6v10M70 6v10M98 6v10M110 16h-20v20"/>
        <circle cx="26" cy="38" r="4"/><circle cx="86" cy="38" r="4"/>
    </g>
    {{-- parada --}}
    <g transform="translate(588 4)">
        <path d="M12 54V16M0 4h24v12H0z"/>
    </g>
</svg>
