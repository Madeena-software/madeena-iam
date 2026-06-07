<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceSessionController extends Controller
{
    /**
     * Display a listing of the user's active device sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Determine current session ID from various potential sources
        $currentSessionId = $request->header('X-Session-ID')
            ?? $request->input('sso_session_id')
            ?? ($request->hasSession() ? $request->session()->getId() : null);

        $sessions = Session::where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get();

        $formattedSessions = $sessions->map(function (Session $session) use ($currentSessionId) {
            return [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'device_details' => $session->device_details,
                'last_activity' => $session->last_activity,
                'last_activity_date' => date('Y-m-d H:i:s', $session->last_activity),
                'is_current' => $currentSessionId !== null && $session->id === $currentSessionId,
            ];
        });

        return response()->json($formattedSessions);
    }

    /**
     * Terminate the specified active device session.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $session = Session::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'Session not found or unauthorized',
            ], 404);
        }

        // Determine current session ID to check if the user is revoking their own current session
        $currentSessionId = $request->header('X-Session-ID')
            ?? $request->input('sso_session_id')
            ?? ($request->hasSession() ? $request->session()->getId() : null);

        $isCurrent = $currentSessionId !== null && $session->id === $currentSessionId;

        $session->delete();

        // If revoking current session, invalidate local session state
        if ($isCurrent) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->json([
            'message' => 'Session terminated successfully',
            'is_current_terminated' => $isCurrent,
        ]);
    }
}
