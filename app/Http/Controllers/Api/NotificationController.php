<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * =====================================================
     * GET ALL NOTIFICATIONS
     * =====================================================
     *
     * GET /api/notifications
     */
    public function index(
        Request $request
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                ],
                'unread_count' => 0,
            ], 401);
        }

        try {

            $perPage =
                (int) $request->input(
                    'per_page',
                    20
                );

            $perPage = max(
                1,
                min($perPage, 100)
            );

            $notifications =
                $user
                    ->notifications()
                    ->latest()
                    ->paginate($perPage);

            $unreadCount =
                $user
                    ->unreadNotifications()
                    ->count();

            return response()->json([
                'success' => true,
                'message' =>
                    'Notifications retrieved successfully.',
                'data' =>
                    $notifications->items(),
                'unread_count' =>
                    $unreadCount,
                'pagination' => [
                    'current_page' =>
                        $notifications
                            ->currentPage(),

                    'last_page' =>
                        $notifications
                            ->lastPage(),

                    'per_page' =>
                        $notifications
                            ->perPage(),

                    'total' =>
                        $notifications
                            ->total(),
                ],
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to retrieve notifications.',
                'data' => [],
                'unread_count' => 0,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                ],
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * =====================================================
     * GET UNREAD
     * =====================================================
     *
     * GET /api/notifications/unread
     */
    public function unread(
        Request $request
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
                'data' => [],
                'count' => 0,
            ], 401);
        }

        try {

            $notifications =
                $user
                    ->unreadNotifications()
                    ->latest()
                    ->get();

            return response()->json([
                'success' => true,
                'message' =>
                    'Unread notifications retrieved successfully.',
                'data' => $notifications,
                'count' =>
                    $notifications->count(),
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to retrieve unread notifications.',
                'data' => [],
                'count' => 0,
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * =====================================================
     * UNREAD COUNT
     * =====================================================
     *
     * GET /api/notifications/unread-count
     */
    public function unreadCount(
        Request $request
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
                'data' => [
                    'count' => 0,
                ],
                'count' => 0,
            ], 401);
        }

        try {

            $count =
                $user
                    ->unreadNotifications()
                    ->count();

            return response()->json([
                'success' => true,
                'message' =>
                    'Unread notification count retrieved successfully.',
                'data' => [
                    'count' => $count,
                ],
                'count' => $count,
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to retrieve unread notification count.',
                'data' => [
                    'count' => 0,
                ],
                'count' => 0,
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * =====================================================
     * MARK ONE AS READ
     * =====================================================
     *
     * PUT /api/notifications/{id}/read
     */
    public function markAsRead(
        Request $request,
        string $id
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        try {

            $notification =
                $user
                    ->notifications()
                    ->where('id', $id)
                    ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Notification not found.',
                ], 404);
            }

            if (!$notification->read_at) {
                $notification->markAsRead();
            }

            return response()->json([
                'success' => true,
                'message' =>
                    'Notification marked as read.',
                'data' => $notification,
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to mark notification as read.',
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * =====================================================
     * MARK ALL AS READ
     * =====================================================
     *
     * PUT /api/notifications/read-all
     */
    public function markAllAsRead(
        Request $request
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        try {

            $user
                ->unreadNotifications()
                ->update([
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'All notifications marked as read.',
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to mark all notifications as read.',
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * =====================================================
     * DELETE ONE
     * =====================================================
     *
     * DELETE /api/notifications/{id}
     */
    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        try {

            $notification =
                $user
                    ->notifications()
                    ->where('id', $id)
                    ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Notification not found.',
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' =>
                    'Notification deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to delete notification.',
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * =====================================================
     * DELETE ALL
     * =====================================================
     *
     * DELETE /api/notifications
     */
    public function destroyAll(
        Request $request
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        try {

            $user
                ->notifications()
                ->delete();

            return response()->json([
                'success' => true,
                'message' =>
                    'All notifications deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to delete notifications.',
                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }
}