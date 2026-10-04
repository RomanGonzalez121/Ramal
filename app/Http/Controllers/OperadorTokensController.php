<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Los tokens de la API pública: cada operador crea los suyos, los ve una sola vez y los puede revocar.
 */
class OperadorTokensController extends Controller
{
    public function index(Request $request): View
    {
        return view('operador.api', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'maximo' => config('ramal.api.tokens_por_operador'),
            'limite' => config('ramal.api.limite_por_minuto'),
        ]);
    }

    public function crear(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:60'],
        ], [
            'nombre.required' => 'Ponele un nombre al token para reconocerlo después.',
            'nombre.min' => 'El nombre es muy corto.',
            'nombre.max' => 'El nombre es muy largo (60 letras como máximo).',
        ]);

        if ($request->user()->tokens()->count() >= config('ramal.api.tokens_por_operador')) {
            return back()->withErrors(['nombre' => 'Ya tenés '.config('ramal.api.tokens_por_operador').' tokens. Revocá uno para crear otro.'])->withInput();
        }

        $token = $request->user()->createToken($datos['nombre'], ['leer']);

        // El valor completo solo existe en este momento: después queda guardado únicamente su hash.
        return redirect()->route('operador.api')->with('token_nuevo', [
            'nombre' => $datos['nombre'],
            'valor' => $token->plainTextToken,
        ]);
    }

    public function revocar(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->firstOrFail()->delete();

        return redirect()->route('operador.api')->with('estado', 'Token revocado. Deja de funcionar ya.');
    }
}
