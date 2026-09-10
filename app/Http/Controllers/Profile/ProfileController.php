<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

/**
 * Controlador de perfil de cuenta (cualquier rol autenticado): edición de
 * datos propios, actualización y eliminación de la propia cuenta.
 */
class ProfileController extends Controller
{
    /**
     * Actualiza el perfil del usuario autenticado. Si cambia el email,
     * exige reverificarlo (invalida email_verified_at).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Cambia el tema visual del usuario autenticado (ver resources/css/app.css
     * para las 4 variantes). Endpoint sin vista propia (layouts/app.blade.php
     * se retiró el 2026-09-10 junto al resto del panel de perfil/notificaciones
     * en Blade); se deja vivo como el resto de acciones de sesión web.
     */
    public function updateTheme(Request $request): RedirectResponse
    {
        $request->validate([
            'theme' => ['required', 'string', 'in:noir,acero,salon,libreta'],
        ]);

        $request->user()->update(['theme' => $request->string('theme')->toString()]);

        return back()->with('status', 'Tema actualizado correctamente.');
    }

    /**
     * Elimina la cuenta del propio usuario, tras confirmar su contraseña actual.
     * Cierra sesión y regenera el token CSRF para evitar fijación de sesión.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
