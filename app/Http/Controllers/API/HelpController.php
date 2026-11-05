<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareerModule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Guía de inicio y asistente de ayuda. La pregunta se compara con config/help.php; no sale del sistema ni se guarda. */
class HelpController extends Controller
{
    private const MAX_RESULTS = 5;

    /** Palabras que no distinguen un tema de otro. */
    private const STOPWORDS = ['como', 'cual', 'cuales', 'cuando', 'con', 'del', 'las', 'los', 'por', 'para', 'puedo', 'que', 'quiero', 'una', 'uno', 'unos', 'unas', 'hago', 'hacer', 'esta', 'este', 'esto', 'donde', 'tengo', 'necesito', 'mis', 'mi', 'me', 'el', 'la', 'en', 'de', 'un', 'al', 'lo', 'se', 'si', 'no', 'y', 'o', 'a'];

    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $audiences = $this->audiences($user);
        $enabled = CareerModule::query()->where('habilitado', true)->pluck('modulo')->all();

        $topics = collect(config('help.topics'))
            ->filter(fn (array $topic) => in_array($topic['audience'], $audiences, true))
            ->filter(fn (array $topic) => empty($topic['module']) || in_array($topic['module'], $enabled, true))
            ->values();

        $question = mb_substr(trim((string) $request->query('q', '')), 0, 200);
        $page = (string) $request->query('page', '');
        if ($question !== '') {
            $selected = $this->match($topics->all(), $question);
        } else {
            // Sin pregunta: primero lo de la pantalla actual y luego lo propio del perfil, antes que lo general.
            $selected = $topics
                ->sortBy(fn (array $topic) => [$this->isOnPage($topic, $page) ? 0 : 1, $topic['audience'] === 'all' ? 1 : 0])
                ->take(self::MAX_RESULTS)->values()->all();
        }

        return response()->json([
            'question' => $question,
            'matched' => $question === '' || $selected !== [],
            'topics' => array_map(fn (array $topic) => [
                'id' => $topic['id'],
                'question' => $topic['question'],
                'steps' => $topic['steps'],
                'app' => $topic['app'] ?? null,
                'web' => $topic['web'] ?? null,
                'here' => $this->isOnPage($topic, $page),
            ], $selected),
            'tour' => $this->tour($audiences),
        ]);
    }

    /** @return list<string> */
    private function audiences(User $user): array
    {
        $profile = (int) $user->perfil_id;

        return array_values(array_filter([
            'all',
            $profile === 3 ? 'student' : null,
            $profile === 2 ? 'teacher' : null,
            in_array($profile, [1, 5, 6, 7], true) ? 'manager' : null,
            in_array($profile, [1, 5, 6], true) ? 'academic' : null,
            in_array($profile, [1, 5], true) ? 'admin' : null,
            $profile === 1 ? 'general' : null,
        ]));
    }

    private function isOnPage(array $topic, string $page): bool
    {
        return $page !== '' && in_array($page, [$topic['app'] ?? null, $topic['web'] ?? null], true);
    }

    /** @return list<array> Temas ordenados por coincidencia con la pregunta. */
    private function match(array $topics, string $question): array
    {
        $words = $this->words($question);
        if ($words === []) {
            return [];
        }

        $scored = [];
        foreach ($topics as $topic) {
            $keywords = $this->words(implode(' ', $topic['keywords']));
            $title = $this->words($topic['question']);
            $body = $this->words(implode(' ', $topic['steps']));
            $score = 0;
            foreach ($words as $word) {
                $score += match (true) {
                    $this->contains($keywords, $word) => 4,
                    $this->contains($title, $word) => 3,
                    $this->contains($body, $word) => 1,
                    default => 0,
                };
            }
            // Se exige más que una mención de paso en el texto, para no responder cualquier cosa.
            if ($score >= 3) {
                $scored[] = ['score' => $score, 'topic' => $topic];
            }
        }
        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_map(fn (array $row) => $row['topic'], array_slice($scored, 0, self::MAX_RESULTS));
    }

    /** @return list<string> Palabras sin acentos ni mayúsculas. */
    private function words(string $text): array
    {
        $plain = Str::lower(Str::ascii($text));
        $words = preg_split('/[^a-z0-9]+/', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_diff($words, self::STOPWORDS));
    }

    /** Coincidencia por raíz, para aceptar plurales y conjugaciones: «entregables» con «entregable», «subo» con «subir». */
    private function contains(array $words, string $word): bool
    {
        $root = mb_substr($word, 0, max(3, mb_strlen($word) - 2));
        foreach ($words as $candidate) {
            if ($candidate === $word || (mb_strlen($word) >= 3 && str_starts_with($candidate, $root))) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $audiences */
    private function tour(array $audiences): array
    {
        $role = in_array('general', $audiences, true) ? 'general'
            : (in_array('manager', $audiences, true) ? 'manager'
            : (in_array('teacher', $audiences, true) ? 'teacher' : 'student'));

        $steps = array_values(array_filter(config('help.tour.steps'), fn (array $step) => in_array($step['audience'] ?? 'all', $audiences, true)));

        return [
            ['target' => null, 'title' => 'Te damos la bienvenida al SGPI', 'text' => config("help.tour.intro.{$role}")],
            ['target' => 'menu', 'title' => 'El menú', 'text' => config("help.tour.menu.{$role}")],
            ...array_map(fn (array $step) => ['target' => $step['target'], 'title' => $step['title'], 'text' => $step['text']], $steps),
        ];
    }
}
