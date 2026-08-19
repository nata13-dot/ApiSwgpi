<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ContinuityPolicyService;
use Illuminate\Http\Request;

class ContinuityPolicyController extends Controller
{
    public function show(ContinuityPolicyService $policies)
    {
        return response()->json($policies->get());
    }

    public function update(Request $request, ContinuityPolicyService $policies)
    {
        $validated = $request->validate([
            'target_readiness' => 'required|integer|min:50|max:100',
            'critical_readiness' => 'required|integer|min:1|lt:target_readiness',
            'max_backup_age_hours' => 'required|integer|min:1|max:168',
            'backup_retention_days' => 'required|integer|min:7|max:365',
        ]);

        return response()->json([
            'message' => 'Política de continuidad actualizada.',
            'policy' => $policies->update($validated, (string) $request->user('api')->id),
        ]);
    }
}
