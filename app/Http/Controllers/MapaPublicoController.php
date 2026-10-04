<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class MapaPublicoController extends Controller
{
    public function __invoke(): View
    {
        return view('mapa');
    }
}
