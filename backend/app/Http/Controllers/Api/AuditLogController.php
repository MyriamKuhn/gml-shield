<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a paginated, read-only audit log for active admins.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Authorization is enforced by the backend.
        if (
            !$user
            || !$user->is_active
            || $user->role !== UserRole::ADMIN
        ) {
            abort(403);
        }

        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);

        $auditLogs = AuditLog::query()
            ->select([
                'id',
                'user_id',
                'action',
                'ip_address',
                'timestamp',
                'event_id',
            ])
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($auditLogs);
    }
}
