<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function store(Request $request, PushNotificationService $push)
    {
        $validated = $request->validate(['token' => 'required|string|min:20|max:4096', 'platform' => 'nullable|in:android,ios']);
        $push->registerDevice((string) auth('api')->id(), $validated['token'], $validated['platform'] ?? 'android');

        return response()->json(['message' => 'Dispositivo registrado.']);
    }

    public function destroy(Request $request, PushNotificationService $push)
    {
        $validated = $request->validate(['token' => 'required|string|max:4096']);
        $push->removeDevice((string) auth('api')->id(), $validated['token']);

        return response()->json(['message' => 'Dispositivo eliminado.']);
    }
}
