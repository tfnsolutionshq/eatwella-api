<?php

namespace App\Http\Controllers;

use App\Models\FcmToken;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // ─── In-App Notifications ─────────────────────────────────────────────────

    /**
     * GET /notifications
     * List the authenticated user's notifications.
     * ?per_page=N  → paginated (recommended)
     * omit per_page → return all
     * ?unread=1    → filter to unread only
     */
    public function index(Request $request)
    {
        $query = Notification::forUser($request->user()->id)->latest();

        if ($request->boolean('unread')) {
            $query->unread();
        }

        if ($request->filled('outlet_id')) {
            $query->where('data->outlet_id', $request->outlet_id);
        }

        if ($request->filled('per_page')) {
            return $query->paginate((int) $request->per_page);
        }

        return $query->get();
    }

    /**
     * GET /notifications/unread-count
     */
    public function unreadCount(Request $request)
    {
        $count = Notification::forUser($request->user()->id)->unread()->count();

        return response()->json(['count' => $count]);
    }

    /**
     * PATCH /notifications/{notification}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, Notification $notification)
    {
        $this->authorizeOwner($request, $notification);

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.', 'notification' => $notification]);
    }

    /**
     * PATCH /notifications/read-all
     * Mark all of the authenticated user's notifications as read.
     */
    public function markAllRead(Request $request)
    {
        Notification::forUser($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * DELETE /notifications/{notification}
     * Delete a single notification.
     */
    public function destroy(Request $request, Notification $notification)
    {
        $this->authorizeOwner($request, $notification);

        $notification->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }

    /**
     * DELETE /notifications
     * Bulk delete notifications.
     * Body: { "ids": ["uuid1", "uuid2"] }  → delete specific notifications
     * Body: { "all": true }                → delete all user notifications
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'uuid',
            'all'   => 'nullable|boolean',
        ]);

        $query = Notification::forUser($request->user()->id);

        if ($request->boolean('all')) {
            $deleted = $query->delete();
        } elseif (! empty($validated['ids'])) {
            $deleted = $query->whereIn('id', $validated['ids'])->delete();
        } else {
            return response()->json(['message' => 'Provide either "ids" or "all: true".'], 422);
        }

        return response()->json(['message' => "{$deleted} notification(s) deleted."]);
    }

    // ─── Push Toggle ─────────────────────────────────────────────────────────

    /**
     * PATCH /notifications/push-settings
     * Enable or disable push notifications for the authenticated user.
     */
    public function pushSettings(Request $request)
    {
        $validated = $request->validate([
            'push_notifications_enabled' => 'required|boolean',
        ]);

        $request->user()->update([
            'push_notifications_enabled' => $validated['push_notifications_enabled'],
        ]);

        $status = $validated['push_notifications_enabled'] ? 'enabled' : 'disabled';

        return response()->json(['message' => "Push notifications {$status}."]);
    }

    // ─── FCM Token Management ─────────────────────────────────────────────────

    /**
     * POST /fcm-tokens
     * Register (or refresh) an FCM token for the current device.
     */
    public function registerToken(Request $request)
    {
        $validated = $request->validate([
            'token'       => 'required|string',
            'device_type' => 'nullable|in:android,ios,web',
        ]);

        FcmToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $validated['token']],
            ['device_type' => $validated['device_type'] ?? null]
        );

        return response()->json(['message' => 'FCM token registered.']);
    }

    /**
     * DELETE /fcm-tokens
     * Remove an FCM token (call on logout to stop push to this device).
     */
    public function removeToken(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        FcmToken::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json(['message' => 'FCM token removed.']);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function authorizeOwner(Request $request, Notification $notification): void
    {
        if ((string) $notification->user_id !== (string) $request->user()->id) {
            abort(403, 'Forbidden.');
        }
    }
}
