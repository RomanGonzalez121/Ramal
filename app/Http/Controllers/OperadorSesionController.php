<?php

namespace App\Http\Controllers;

use App\Models\Linea;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Entrar y salir del panel de operador.
 */
class OperadorSesionController extends Controller
{
    public function formulario(): View
    {
        return view('operador.ingresar', [
            'lineas' => Linea::orderBy('numero')->get(['numero', 'destino']),
        ]);
    }

    public function entrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Escribí tu correo.',
            'email.email' => 'Ese correo no parece válido.',
            'password.required' => 'Escribí tu contraseña.',
        ]);

        // Solo entran cuentas con rol de operador; las demás reciben el mismo mensaje que una contraseña mala.
        $entro = Auth::attempt([...$datos, 'rol' => 'operador'], $request->boolean('recordar'));

        if (! $entro) {
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no coinciden con una cuenta de operador.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('operador'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('mapa');
    }
}
