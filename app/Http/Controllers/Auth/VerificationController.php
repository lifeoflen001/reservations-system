<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InvitationService;
use App\Services\OnboardingService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function notice(): View|RedirectResponse
    {
        return auth()->user()->hasVerifiedEmail() ? redirect()->route('post-auth') : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->markEmailAsVerified()) event(new Verified($request->user()));
        $token = $request->session()->pull('pending_invitation_token');
        if ($token) {
            app(InvitationService::class)->accept($request->user(), $token);
            return redirect()->route('post-auth')->with('success', 'Your invitation was accepted.');
        }
        return redirect()->route('post-auth')->with('success', 'Your email address is verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) return redirect()->route('post-auth');
        $request->user()->sendEmailVerificationNotification();
        return back()->with('success', 'If the account can receive mail, a new verification link has been sent.');
    }
}
