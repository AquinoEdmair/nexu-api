<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EmailVerificationController extends Controller
{
    /**
     * Verify the user's email from the signed URL sent by email.
     * Redirects to the frontend after success/failure.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

        if (! $request->hasValidSignature()) {
            return redirect()->away($frontendUrl . '/email-verified?status=invalid');
        }

        /** @var User|null $user */
        $user = User::find($id);

        if ($user === null) {
            return redirect()->away($frontendUrl . '/email-verified?status=not_found');
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->away($frontendUrl . '/email-verified?status=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->away($frontendUrl . '/email-verified?status=already');
        }

        $user->markEmailAsVerified();
        $user->update(['status' => 'active']);
        event(new Verified($user));

        return redirect()->away($frontendUrl . '/email-verified?status=success');
    }

    /**
     * Resend the verification email to the authenticated user.
     */
    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('Email already verified.'),
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => __('Verification email sent.'),
        ]);
    }

    /**
     * Resend the verification email by email address (pre-login scenario).
     */
    public function resendByEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        // Always respond the same way to prevent enumeration
        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => __('If the email exists and is not verified, a new link has been sent.'),
        ]);
    }
}
