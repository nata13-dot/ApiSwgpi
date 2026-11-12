<?php

namespace App\Support;

use App\Models\EvaluationRoom;
use Illuminate\Support\Carbon;

/**
 * Tiempos de una sala de evaluación. Cada turno es exposición + preguntas y respuestas; entre un proyecto y el
 * siguiente hay una tolerancia (3 min por defecto). La hora de fin de la sala se calcula, no se captura.
 */
final class RoomTimeline
{
    public const DEFAULT_TOLERANCE = 3;
    /** Segundos en rojo antes de pasar solas a preguntas cuando la sala lo tiene activado. */
    public const QUESTIONS_DELAY = 5;

    public static function turnMinutes(int $presentation, int $questions): int
    {
        return max(0, $presentation) + max(0, $questions);
    }

    /** Fin de la sala: n turnos completos más la tolerancia entre ellos (no después del último). */
    public static function endFor(Carbon $start, int $projects, int $presentation, int $questions, int $tolerance): Carbon
    {
        $projects = max(1, $projects);

        return $start->copy()->addMinutes($projects * self::turnMinutes($presentation, $questions) + ($projects - 1) * max(0, $tolerance));
    }

    public static function tolerance(EvaluationRoom $room): int
    {
        return (int) ($room->tolerance_minutes ?? self::DEFAULT_TOLERANCE);
    }

    /**
     * Si la exposición terminó y la sala pasa sola a preguntas, deja el temporizador en la fase de preguntas
     * (empezó QUESTIONS_DELAY segundos después de terminar la exposición). Devuelve true si cambió algo.
     */
    public static function syncPhase(EvaluationRoom $room): bool
    {
        if ($room->timer_status !== 'en_curso' || ($room->timer_phase ?? 'exposicion') !== 'exposicion' || !$room->auto_questions || !$room->timer_ends_at) {
            return false;
        }
        $questionsStart = $room->timer_ends_at->copy()->addSeconds(self::QUESTIONS_DELAY);
        if (now()->lt($questionsStart)) {
            return false;
        }
        $duration = max(60, ((int) $room->teacher_evaluation_minutes) * 60);
        $room->update([
            'timer_phase' => 'preguntas',
            'timer_duration_seconds' => $duration,
            'timer_started_at' => $questionsStart,
            'timer_ends_at' => $questionsStart->copy()->addSeconds($duration),
            'timer_remaining_seconds' => null,
        ]);

        return true;
    }

    /**
     * Minutos que faltan para que empiece el turno `order`, según cómo va la sala ahora mismo.
     * null si ya pasó; 0 si es el turno en curso.
     */
    public static function minutesUntil(EvaluationRoom $room, int $order, int $timerRemaining): ?int
    {
        $turn = self::turnMinutes((int) $room->project_presentation_minutes, (int) $room->teacher_evaluation_minutes);
        $slot = $turn + self::tolerance($room);
        if (!$room->sequence_locked || !$room->current_order) {
            $start = $room->fecha_evaluacion ? max(0, (int) ceil(now()->diffInSeconds($room->fecha_evaluacion, false) / 60)) : 0;

            return $start + ($order - 1) * $slot;
        }
        $current = (int) $room->current_order;
        if ($room->completed_at || $order < $current) return null;
        if ($order === $current) return 0;
        // Lo que le queda al turno en curso: el temporizador y, si va en exposición, todas las preguntas.
        $remaining = $timerRemaining / 60;
        if (($room->timer_phase ?? 'exposicion') === 'exposicion') {
            $remaining += (int) $room->teacher_evaluation_minutes;
            if ($room->timer_status === 'detenido' && !$room->timer_started_at) $remaining = $turn;
        }

        return (int) ceil($remaining + self::tolerance($room) + ($order - $current - 1) * $slot);
    }
}
