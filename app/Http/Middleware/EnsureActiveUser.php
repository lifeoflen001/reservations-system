<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_active === false) { Auth::logout(); $request->session()->invalidate(); return redirect()->route('login')->with('error', 'This staff account is inactive.'); }
        return $next($request);
    }
}
