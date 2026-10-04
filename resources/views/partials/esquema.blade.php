{{-- Las propiedades de un esquema OpenAPI como lista con sangría: nombre, tipo y qué es. Se llama a sí misma para los objetos anidados. --}}
@php
    $obligatorias = $esquema['required'] ?? [];
    $propiedades = $esquema['properties'] ?? [];
@endphp

<dl class="{{ $nivel > 0 ? 'ml-3 border-l-[3px] border-dashed border-texto/30 pl-3' : 'divide-y-2 divide-dashed divide-texto/25' }}">
    @foreach ($propiedades as $nombre => $campo)
        @php
            $interno = $campo['type'] ?? null;
            $hijo = ($campo['properties'] ?? false) ? $campo : (($campo['items']['properties'] ?? false) ? $campo['items'] : null);
        @endphp
        <div class="py-2">
            <div class="grid gap-x-4 gap-y-0.5 sm:grid-cols-[13rem_1fr]">
                <dt class="flex flex-wrap items-baseline gap-x-2"><span class="codigo font-bold">{{ $nombre }}</span></dt>
                <dd class="text-[0.97rem]">
                    <span class="text-apoyo">{{ $doc->tipo($campo) }}{{ in_array($nombre, $obligatorias, true) ? '' : ', opcional' }}</span>
                    @if (! empty($campo['description'])) <span class="block">{{ $campo['description'] }}</span> @endif
                    @if (! empty($campo['enum'])) <span class="mt-1 flex flex-wrap gap-1.5">@foreach ($campo['enum'] as $opcion)<span class="codigo rounded-sm bg-superficie px-1.5 py-0.5 text-sm">{{ $opcion }}</span>@endforeach</span> @endif
                </dd>
            </div>
            @if ($hijo && $nivel < 4)
                <div class="mt-1">@include('partials.esquema', ['esquema' => $hijo, 'doc' => $doc, 'nivel' => $nivel + 1])</div>
            @endif
        </div>
    @endforeach
</dl>
