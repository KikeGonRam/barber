<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controla el inicio y cierre de sesión web (guard "web") para todos los roles del dashboard.
 * No aplica a la API móvil, que usa AuthController con tokens Sanctum.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Muestra el formulario de login del dashboard.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Autentica al usuario y arranca la sesión web.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // La validación de credenciales vive en LoginRequest::authenticate()
        $request->authenticate();

        // Regenerar el ID de sesión evita fijación de sesión (session fixation) tras el login
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Cierra la sesión del usuario y limpia el estado de sesión/CSRF.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        // Nuevo token CSRF para que la sesión anterior no pueda reutilizarse
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
