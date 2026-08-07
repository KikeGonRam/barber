<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Verifica el email del usuario autenticado mediante un código numérico de 6 dígitos
 * (alternativa al enlace de verificación clásico de Laravel).
 */
class VerifyEmailCodeController extends Controller
{
    /**
     * Compara el código recibido contra el guardado en el usuario y, si es válido y no expiró,
     * marca el email como verificado.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // hash_equals evita timing attacks al comparar el código
        $valid = $user->verification_code
            && hash_equals($user->verification_code, $request->string('code')->toString())
            && $user->verification_code_expires_at
            && $user->verification_code_expires_at->isFuture();

        if (! $valid) {
            return back()->withErrors(['code' => 'Código inválido o expirado.']);
        }

        // Se limpia el código una vez usado para que no pueda reintentarse
        $user->forceFill([
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();

        $user->markEmailAsVerified();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
