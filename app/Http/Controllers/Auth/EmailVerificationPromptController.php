<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador invocable que actúa como gate: muestra la pantalla de "verifica tu correo"
 * o deja pasar al dashboard si el usuario ya lo verificó.
 */
class EmailVerificationPromptController extends Controller
{
    /**
     * Redirige al dashboard si el email ya está verificado; si no, muestra el aviso de verificación.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('dashboard', absolute: false))
                    : view('auth.verify-email');
    }
}
