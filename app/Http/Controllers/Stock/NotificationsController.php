<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\StockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function __construct(
        private StockAlertService $alertService,
    ) {}

    /**
     * Display the notifications page.
     */
    public function index(Request $request): Response
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('stock/notifications', [
            'notifications' => $notifications,
            'unread_count' => $this->alertService->unreadCount($request->user()),
        ]);
    }

    /**
     * Get the unread notification count (for the bell icon).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->alertService->unreadCount($request->user()),
        ]);
    }

    /**
     * Get the latest unread notifications (for the dropdown).
     */
    public function latest(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Notification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'link' => $notification->link,
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at,
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $this->alertService->unreadCount($request->user()),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markRead(Notification $notification): JsonResponse
    {
        $notification->markRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read for the current user.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
