<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailCodeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $valid = $user->verification_code
            && hash_equals($user->verification_code, $request->string('code')->toString())
            && $user->verification_code_expires_at
            && $user->verification_code_expires_at->isFuture();

        if (! $valid) {
            return back()->withErrors(['code' => 'Código inválido o expirado.']);
        }

        $user->forceFill([
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();

        $user->markEmailAsVerified();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
