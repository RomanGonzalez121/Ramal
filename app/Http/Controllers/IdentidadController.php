<?php

namespace App\Http\Controllers;

use App\Support\Contraste;
use Illuminate\Contracts\View\View;

class IdentidadController extends Controller
{
    public function __invoke(): View
    {
        $papel = config('identidad.papel');
        $asfalto = config('identidad.asfalto');

        $colores = collect(config('identidad.colores'))->map(function (array $color) use ($papel, $asfalto) {
            $color['ratio_dia'] = ! ($color['superficie'] ?? false) && $color['dia'] ? Contraste::formato($color['dia'], $papel) : null;
            $color['ratio_noche'] = ! ($color['superficie'] ?? false) && $color['noche'] ? Contraste::formato($color['noche'], $asfalto) : null;

            return $color;
        });

        return view('identidad', [
            'colores' => $colores,
            'senalSobreAsfalto' => Contraste::formato('#F5C400', $asfalto),
            'senalSobrePapel' => Contraste::formato('#F5C400', $papel),
        ]);
    }
}
