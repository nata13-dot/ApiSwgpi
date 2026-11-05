<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Un evento no puede abrirse en una fecha y hora ya pasadas. Si ya estaba guardado con ese mismo valor se acepta,
 * para poder editar los demás datos de algo que ya comenzó.
 */
class NotBeforeNow implements ValidationRule
{
    public function __construct(private readonly mixed $current = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $moment = Carbon::parse($value);
        } catch (\Throwable) {
            return; // La regla `date` ya informa del formato inválido.
        }

        // Los campos de fecha sola se comparan por día; los de fecha y hora, por minuto, que es lo que captura el formulario.
        $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $value)) === 1;
        $unit = $dateOnly ? 'day' : 'minute';
        if ($this->current && $moment->isSameAs($dateOnly ? 'Y-m-d' : 'Y-m-d H:i', Carbon::parse($this->current))) {
            return;
        }

        if ($moment->lt(now()->startOf($unit))) {
            $fail($dateOnly
                ? 'La fecha no puede ser anterior a hoy.'
                : 'La fecha y hora no pueden ser anteriores al momento actual.');
        }
    }
}
