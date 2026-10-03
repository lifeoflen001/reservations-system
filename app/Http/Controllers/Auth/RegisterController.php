<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::check()) return redirect()->route('dashboard');
        return view('auth.register', ['invitationToken' => $request->session()->get('pending_invitation_token')]);
    }

    public function store(Request $request, RegistrationService $registration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', app(\App\Services\SystemSettingsService::class)->passwordRule()],
            'terms' => ['accepted'],
            'website' => ['nullable', 'size:0'],
        ]);
        $token = $request->session()->get('pending_invitation_token');
        $user = $registration->register($data, $token);
        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();
        return redirect()->route('verification.notice')->with('success', 'Check your email to verify your Lodgix account.');
    }
}
