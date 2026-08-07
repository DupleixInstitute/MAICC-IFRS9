<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Database-notification endpoints backing the header bell: a JSON feed for
 * the dropdown plus read-marking. The unread badge count is shared on every
 * Inertia response (HandleInertiaRequests), so no polling is needed.
 */
class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Recent notifications + unread count for the bell dropdown (JSON). */
    public function recent()
    {
        $user = Auth::user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(8)->get()->map(fn ($n) => $this->present($n)),
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = Auth::user()->notifications()->where('id', $id)->first();
        $notification?->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    private function present($n): array
    {
        $data = $n->data ?? [];

        return [
            'id' => $n->id,
            'type' => $data['type'] ?? 'notification',
            'message' => $data['message'] ?? 'Notification',
            'url' => $data['url'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => optional($n->created_at)->diffForHumans(),
            'created_at_full' => optional($n->created_at)->format('d M Y H:i'),
        ];
    }
}
