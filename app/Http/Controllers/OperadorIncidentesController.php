<?php

namespace App\Http\Controllers;

use App\Models\Incidente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lo que un operador puede hacer con un incidente (M7).
 *
 * Ciclo: activo (se generó) -> atendido (un operador lo tomó) -> resuelto.
 * Quien mueve a los colectivos es el simulador: estas acciones quedan anotadas en el incidente y el
 * simulador las aplica en su próximo tick (a los pocos segundos).
 */
class OperadorIncidentesController extends Controller
{
    /** El operador toma el incidente: habló con el chofer o mandó ayuda. Una falla o una demora se acortan. */
    public function atender(Request $request, Incidente $incidente): JsonResponse
    {
        if ($incidente->estado !== Incidente::ACTIVO) {
            return $this->rechazar('Ese incidente ya está atendido o resuelto.', 409);
        }

        $incidente->update([
            'estado' => Incidente::ATENDIDO,
            'atendido_por' => $request->user()->id,
            'atendido_en' => now(),
        ]);

        return response()->json(['id' => $incidente->id, 'estado' => $incidente->estado, 'mensaje' => 'Incidente atendido.']);
    }

    /** El operador lo da por terminado. No se puede con un desvío: se cierra solo cuando el colectivo vuelve al recorrido. */
    public function resolver(Request $request, Incidente $incidente): JsonResponse
    {
        if (! $incidente->estaAbierto()) {
            return $this->rechazar('Ese incidente ya está resuelto.', 409);
        }

        if ($incidente->tipo === 'desvio') {
            return $this->rechazar('Un desvío se cierra solo cuando el colectivo vuelve al recorrido.', 422);
        }

        if ($incidente->accion_pedida !== null) {
            return response()->json(['id' => $incidente->id, 'estado' => $incidente->estado, 'mensaje' => 'Ya se pidió resolverlo; se aplica en unos segundos.']);
        }

        // Resolver sin haberlo atendido es atenderlo y resolverlo a la vez.
        $incidente->update(array_filter([
            'accion_pedida' => 'resolver',
            'estado' => Incidente::ATENDIDO,
            'atendido_por' => $incidente->atendido_por ?? $request->user()->id,
            'atendido_en' => $incidente->atendido_en ?? now(),
            'ajuste_aplicado' => true,
        ], fn ($valor) => $valor !== null));

        return response()->json(['id' => $incidente->id, 'estado' => $incidente->estado, 'mensaje' => 'Se pidió resolverlo; se aplica en unos segundos.'], 202);
    }

    private function rechazar(string $mensaje, int $codigo): JsonResponse
    {
        return response()->json(['mensaje' => $mensaje], $codigo);
    }
}
