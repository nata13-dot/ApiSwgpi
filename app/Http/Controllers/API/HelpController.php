<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareerModule;
use App\Models\User;
use App\Services\HelpPersonalDataService;
use App\Support\HelpCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Guía de inicio y asistente de ayuda: árbol de temas y preguntas libres que se comparan con resources/help.
 * No usa inteligencia artificial, y la pregunta no sale del sistema ni se guarda.
 */
class HelpController extends Controller
{
    private const MAX_SUGGESTIONS = 5;
    private const MAX_OPTIONS = 4;

    /** Palabras que no distinguen un tema de otro. */
    private const STOPWORDS = ['como', 'cual', 'cuales', 'cuando', 'con', 'del', 'las', 'los', 'por', 'para', 'puedo', 'que', 'quiero', 'una', 'uno', 'unos', 'unas', 'hago', 'hacer', 'esta', 'este', 'esto', 'donde', 'tengo', 'necesito', 'mis', 'mi', 'me', 'el', 'la', 'en', 'de', 'un', 'al', 'lo', 'se', 'si', 'no', 'y', 'o', 'a', 'es', 'son', 'hay', 'ya', 'sale', 'dice', 'ayuda', 'favor', 'quien', 'pero', 'porque', 'tu', 'te', 'le', 'su', 'sus', 'mas'];

    public function __construct(private readonly HelpPersonalDataService $personal) {}

    public function index(Request $request): JsonResponse
    {
        $audiences = $this->audiences(auth('api')->user());
        $enabled = CareerModule::query()->where('habilitado', true)->pluck('modulo')->all();
        $topics = array_filter(HelpCatalog::topics(), fn (array $topic) => $this->visible($topic, $audiences, $enabled));

        return response()->json($this->answer($request, $topics, $audiences) + ['tour' => $this->tour($audiences)]);
    }

    /** Sin sesión solo se ofrecen los temas de acceso y registro. */
    public function publicIndex(Request $request): JsonResponse
    {
        $topics = array_filter(HelpCatalog::topics(), fn (array $topic) => $this->audienceOf($topic) === ['guest']);

        return response()->json($this->answer($request, $topics, ['guest']));
    }

    private function answer(Request $request, array $topics, array $audiences): array
    {
        $page = (string) $request->query('page', '');
        $topicId = (string) $request->query('topic', '');
        $question = mb_substr(trim((string) $request->query('q', '')), 0, 200);

        if ($topicId !== '') {
            abort_unless(isset($topics[$topicId]), 404, 'Ese tema no está disponible para tu perfil.');

            return ['topic' => $this->detail($topics[$topicId], $topics, $page, $audiences)];
        }

        if ($question !== '') {
            [$best, $options, $confident] = $this->match($topics, $question);

            return [
                'question' => $question,
                'confidence' => $best === null ? 'none' : ($confident ? 'high' : 'maybe'),
                'topic' => $best && $confident ? $this->detail($best, $topics, $page, $audiences) : null,
                'options' => array_map(fn (array $topic) => $this->summary($topic), $confident ? $options : array_filter([$best, ...$options])),
            ];
        }

        return [
            'categories' => $this->categories($topics),
            'suggested' => array_map(fn (array $topic) => $this->summary($topic), $this->suggested($topics, $page)),
        ];
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

    /** @return list<string> */
    private function audienceOf(array $topic): array
    {
        return (array) $topic['audience'];
    }

    private function visible(array $topic, array $audiences, array $enabled): bool
    {
        return array_intersect($this->audienceOf($topic), $audiences) !== []
            && (empty($topic['module']) || in_array($topic['module'], $enabled, true));
    }

    private function isOnPage(array $topic, string $page): bool
    {
        return $page !== '' && in_array($page, [$topic['app'] ?? null, $topic['web'] ?? null], true);
    }

    private function summary(array $topic): array
    {
        return ['id' => $topic['id'], 'question' => $topic['question']];
    }

    private function detail(array $topic, array $topics, string $page, array $audiences): array
    {
        $personal = !empty($topic['dynamic']) ? $this->personal->answer($topic['dynamic']) : null;
        $linked = fn (?string $id) => $id !== null && isset($topics[$id]) ? $this->summary($topics[$id]) : null;

        return [
            'id' => $topic['id'],
            'question' => $topic['question'],
            'category' => $topic['category'],
            'personal' => $personal !== null,
            'steps' => $personal['lines'] ?? $topic['steps'],
            'app' => $personal['app'] ?? $topic['app'] ?? null,
            'web' => $personal['web'] ?? $topic['web'] ?? null,
            'here' => $this->isOnPage($topic, $page),
            'next' => $linked($topic['next'] ?? null),
            'related' => array_values(array_filter(array_map($linked, $topic['related'] ?? []))),
        ];
    }

    /** @return list<array> Agrupados en el orden de HelpCatalog::CATEGORIES. */
    private function categories(array $topics): array
    {
        $groups = [];
        foreach (HelpCatalog::CATEGORIES as $id => $title) {
            $items = array_values(array_filter($topics, fn (array $topic) => $topic['category'] === $id));
            if ($items !== []) {
                $groups[] = ['id' => $id, 'title' => $title, 'topics' => array_map(fn (array $topic) => $this->summary($topic), $items)];
            }
        }

        return $groups;
    }

    /** Lo de la pantalla actual primero, luego la situación de la persona y lo propio de su perfil. */
    private function suggested(array $topics, string $page): array
    {
        $rank = fn (array $topic) => $this->isOnPage($topic, $page) ? 0
            : ($topic['category'] === 'situacion' ? 1 : (in_array('all', $this->audienceOf($topic), true) || $topic['category'] === 'problemas' ? 3 : 2));
        $list = array_values($topics);
        usort($list, fn (array $a, array $b) => $rank($a) <=> $rank($b));

        return array_slice($list, 0, self::MAX_SUGGESTIONS);
    }

    /**
     * Cada palabra suma según dónde aparece. Hay confianza si la mayoría de las palabras coincide; si no, se
     * ofrecen opciones en lugar de responder algo que quizá no se preguntó.
     *
     * @return array{0: ?array, 1: list<array>, 2: bool}
     */
    private function match(array $topics, string $question): array
    {
        $words = $this->words($question);
        if ($words === []) {
            return [null, [], false];
        }

        $scored = [];
        foreach ($topics as $topic) {
            $keywords = $this->words(implode(' ', $topic['keywords'] ?? []));
            $title = $this->words($topic['question'].' '.implode(' ', $topic['variants'] ?? []));
            $body = $this->words(implode(' ', $topic['steps'] ?? []));
            $score = 0;
            $strong = 0;
            foreach ($words as $word) {
                $points = match (true) {
                    $this->contains($keywords, $word) => 4,
                    $this->contains($title, $word) => 3,
                    $this->contains($body, $word) => 1,
                    default => 0,
                };
                $score += $points;
                $strong += $points >= 3 ? 1 : 0;
            }
            // Una frase registrada, dicha casi igual, gana a cualquier suma de palabras sueltas.
            foreach ([$topic['question'], ...($topic['variants'] ?? [])] as $phrase) {
                if ($this->words($phrase) === $words) {
                    $score += 20;
                    $strong = count($words);
                    break;
                }
            }
            if ($strong > 0) {
                $scored[] = ['score' => $score, 'coverage' => $strong / count($words), 'topic' => $topic];
            }
        }
        if ($scored === []) {
            return [null, [], false];
        }
        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score'] ?: $b['coverage'] <=> $a['coverage']);

        $others = array_map(fn (array $row) => $row['topic'], array_slice($scored, 1, self::MAX_OPTIONS));
        $tied = isset($scored[1]) && $scored[1]['score'] === $scored[0]['score'] && count($words) === 1;

        return [$scored[0]['topic'], $others, $scored[0]['coverage'] >= 0.5 && !$tied];
    }

    /** @return list<string> Palabras sin acentos ni mayúsculas. */
    private function words(string $text): array
    {
        $plain = Str::lower(Str::ascii($text));
        $words = preg_split('/[^a-z0-9]+/', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_diff($words, self::STOPWORDS)));
    }

    /** Acepta plurales y conjugaciones («entregables», «subo» por «subir») y faltas leves de ortografía. */
    private function contains(array $words, string $word): bool
    {
        $length = strlen($word);
        $root = substr($word, 0, max(3, $length - 2));
        foreach ($words as $candidate) {
            if ($candidate === $word || ($length >= 3 && str_starts_with($candidate, $root))) {
                return true;
            }
            // Sin compartir el inicio, dos palabras parecidas suelen ser distintas: «residencia» y «evidencia».
            if ($length >= 5 && str_starts_with($candidate, substr($word, 0, 3)) && levenshtein($candidate, $word) <= ($length >= 8 ? 2 : 1)) {
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
