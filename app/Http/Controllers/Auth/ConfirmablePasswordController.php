<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Reconfirma la contraseña del usuario ya autenticado antes de dejarlo entrar
 * a zonas sensibles del dashboard (protegidas por el middleware "password.confirm").
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * Muestra el formulario para reingresar la contraseña.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Valida la contraseña actual del usuario autenticado y marca la confirmación en sesión.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        // Timestamp que el middleware "password.confirm" usa para saber si aún es válida la confirmación
        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
