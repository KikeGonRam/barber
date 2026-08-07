<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Permite al usuario autenticado (cualquier rol) cambiar su propia contraseña
 * desde su perfil, exigiendo la contraseña actual.
 */
class PasswordController extends Controller
{
    /**
     * Verifica la contraseña actual y guarda la nueva contraseña ya hasheada.
     */
    public function update(Request $request): RedirectResponse
    {
        // "current_password" valida contra el hash guardado; se agrupa en el bag "updatePassword"
        // para que el formulario pueda distinguir estos errores de otros en la misma página
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
