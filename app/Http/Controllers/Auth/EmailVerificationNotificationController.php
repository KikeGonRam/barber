<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reenvía el correo/código de verificación de email al usuario autenticado que aún no lo confirmó.
 */
class EmailVerificationNotificationController extends Controller
{
    /**
     * Dispara un nuevo envío de verificación, salvo que el email ya esté verificado.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
