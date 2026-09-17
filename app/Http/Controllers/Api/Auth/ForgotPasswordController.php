<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Send password reset link.
     *
     * POST /api/forgot-password
     */
    public function sendResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'exists:users,email',
            ],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => __($status),
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => __($status),
        ], 400);
    }
}

