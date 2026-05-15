<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubjectGroup;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SystemSettingController extends Controller
{
    public function public()
    {
        $this->purgeExpiredNotices();
        $settings = SystemSetting::allWithDefaults();
        $today = now()->toDateString();

        return response()->json([
            'session_timeout_minutes' => $settings['session_timeout_minutes'],
            'default_theme' => $settings['default_theme'],
            'global_notice' => $settings['global_notice'],
            'font_scale' => $settings['font_scale'],
            'proposal_registration_enabled' => $settings['proposal_registration_enabled'],
            'active_academic_period' => $settings['active_academic_period'],
            'max_file_size_mb' => $settings['max_file_size_mb'],
            'allowed_file_types' => $settings['allowed_file_types'],
            'grayscale_mode' => $settings['grayscale_mode'],
            'system_notices' => collect($settings['system_notices'] ?? [])
                ->filter(fn ($notice) => ($notice['active'] ?? true) && !empty($notice['message']) && $this->noticeIsVisible($notice, $today))
                ->values(),
        ]);
    }

    public function index()
    {
        return response()->json(SystemSetting::allWithDefaults());
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'session_timeout_minutes' => 'required|integer|min:1|max:480',
            'default_theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'active_academic_period' => 'required|string|max:40',
            'max_file_size_mb' => 'required|integer|min:1|max:200',
            'allowed_file_types' => 'required|array|min:1',
            'allowed_file_types.*' => ['string', Rule::in(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'jpg', 'jpeg', 'png'])],
            'max_project_members' => 'required|integer|min:1|max:10',
            'global_notice' => 'nullable|string|max:1000',
            'proposal_registration_enabled' => 'required|boolean',
            'font_scale' => 'required|integer|min:85|max:125',
            'grayscale_mode' => 'required|boolean',
        ]);

        foreach ($validated as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'array',
                default => 'string',
            };
            SystemSetting::setValue($key, $value, $type);
        }

        return response()->json(['message' => 'Ajustes guardados', 'settings' => SystemSetting::allWithDefaults()]);
    }

    public function notices()
    {
        $this->purgeExpiredNotices();
        return response()->json(['data' => SystemSetting::valueFor('system_notices', [])]);
    }

    public function updateNotices(Request $request)
    {
        $validated = $request->validate([
            'notices' => 'present|array',
            'notices.*.id' => 'nullable|string|max:80',
            'notices.*.title' => 'nullable|string|max:90',
            'notices.*.message' => 'required|string|max:500',
            'notices.*.audience' => ['required', Rule::in(['all', 'index', 'authenticated', 'academic', 'teacher', 'student', 'admin'])],
            'notices.*.type' => ['required', Rule::in(['info', 'success', 'warning', 'danger'])],
            'notices.*.duration_seconds' => 'nullable|integer|min:2|max:30',
            'notices.*.starts_at' => 'nullable|date',
            'notices.*.ends_at' => 'nullable|date',
            'notices.*.active' => 'required|boolean',
        ]);

        foreach ($validated['notices'] as $index => $notice) {
            if (!empty($notice['starts_at']) && !empty($notice['ends_at']) && Carbon::parse($notice['ends_at'])->lt(Carbon::parse($notice['starts_at']))) {
                throw ValidationException::withMessages([
                    "notices.{$index}.ends_at" => ['La fecha final del aviso debe ser igual o posterior a la fecha inicial.'],
                ]);
            }
        }

        $notices = collect($validated['notices'])
            ->map(fn ($notice) => [
                'id' => $notice['id'] ?? uniqid('notice_', true),
                'title' => trim($notice['title'] ?? ''),
                'message' => trim($notice['message']),
                'audience' => $notice['audience'],
                'type' => $notice['type'],
                'duration_seconds' => (int) ($notice['duration_seconds'] ?? 4),
                'starts_at' => $notice['starts_at'] ?? null,
                'ends_at' => $notice['ends_at'] ?? null,
                'active' => (bool) $notice['active'],
            ])
            ->filter(fn ($notice) => !$this->noticeExpired($notice, now()->toDateString()))
            ->values()
            ->all();

        SystemSetting::setValue('system_notices', $notices, 'array');

        return response()->json([
            'message' => 'Avisos guardados',
            'data' => $notices,
        ]);
    }

    private function purgeExpiredNotices(): void
    {
        $today = now()->toDateString();
        $notices = SystemSetting::valueFor('system_notices', []);
        $active = collect($notices)
            ->filter(fn ($notice) => !$this->noticeExpired($notice, $today))
            ->values()
            ->all();

        if (count($active) !== count($notices)) {
            SystemSetting::setValue('system_notices', $active, 'array');
        }
    }

    private function noticeExpired(array $notice, string $today): bool
    {
        return !empty($notice['ends_at']) && Carbon::parse($notice['ends_at'])->lt(Carbon::parse($today));
    }

    private function noticeIsVisible(array $notice, string $today): bool
    {
        if (!empty($notice['starts_at']) && Carbon::parse($notice['starts_at'])->gt(Carbon::parse($today))) {
            return false;
        }

        return !$this->noticeExpired($notice, $today);
    }

    public function semesterPreview(Request $request)
    {
        $validated = $request->validate([
            'from_semester' => 'required|integer|in:5,6,7,8',
            'from_group' => 'nullable|string|max:20',
        ]);

        $students = User::where('perfil_id', 3)
            ->where('activo', true)
            ->where('semestre', $validated['from_semester'])
            ->when(!empty($validated['from_group']), fn ($query) => $query->where('grupo', strtoupper($validated['from_group'])))
            ->orderBy('grupo')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apa', 'ama', 'semestre', 'grupo']);

        return response()->json(['students' => $students]);
    }

    public function applySemesterChange(Request $request)
    {
        $validated = $request->validate([
            'from_semester' => 'required|integer|in:5,6,7,8',
            'from_group' => 'nullable|string|max:20',
            'to_semester' => 'required|integer|in:5,6,7,8',
            'to_group' => 'nullable|string|max:20',
            'update_subject_groups' => 'nullable|boolean',
            'exceptions' => 'nullable|array',
            'exceptions.*.user_id' => ['required', 'string', Rule::exists('users', 'id')->where('activo', true)->where('perfil_id', 3)],
            'exceptions.*.semester' => 'required|integer|in:5,6,7,8',
        ]);

        $exceptions = collect($validated['exceptions'] ?? [])
            ->mapWithKeys(fn ($item) => [$item['user_id'] => (int) $item['semester']])
            ->all();

        $summary = DB::transaction(function () use ($validated, $exceptions) {
            $students = User::where('perfil_id', 3)
                ->where('activo', true)
                ->where('semestre', $validated['from_semester'])
                ->when(!empty($validated['from_group']), fn ($query) => $query->where('grupo', strtoupper($validated['from_group'])))
                ->get();

            $updated = 0;
            $exceptionCount = 0;

            foreach ($students as $student) {
                $nextSemester = $exceptions[$student->id] ?? (int) $validated['to_semester'];
                if (isset($exceptions[$student->id])) {
                    $exceptionCount++;
                }
                $nextGroup = !empty($validated['to_group']) ? strtoupper($validated['to_group']) : $student->grupo;
                if ((int) $student->semestre !== $nextSemester || $student->grupo !== $nextGroup) {
                    $student->update(['semestre' => $nextSemester, 'grupo' => $nextGroup]);
                    $updated++;
                }
            }

            $groupsUpdated = 0;
            if ($validated['update_subject_groups'] ?? false) {
                $groupsUpdated = SubjectGroup::where('semestre', $validated['from_semester'])
                    ->when(!empty($validated['from_group']), fn ($query) => $query->where('grupo', strtoupper($validated['from_group'])))
                    ->update(['semestre' => $validated['to_semester']]);
            }

            return [
                'students_reviewed' => $students->count(),
                'students_updated' => $updated,
                'exceptions_applied' => $exceptionCount,
                'subject_groups_updated' => $groupsUpdated,
            ];
        });

        return response()->json(['message' => 'Cambio de semestre aplicado', 'summary' => $summary]);
    }
}
