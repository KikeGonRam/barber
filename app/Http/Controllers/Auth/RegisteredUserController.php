<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registro de nuevos usuarios desde el dashboard web. El primer usuario del sistema
 * puede auto-asignarse como administrador (bootstrap); el resto queda como "cliente".
 */
class RegisteredUserController extends Controller
{
    /**
     * Muestra el formulario de registro.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Crea el usuario, le asigna rol (admin en bootstrap o cliente por defecto)
     * y arranca su sesión autenticada.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Se cuenta antes de crear para saber si este es el primer usuario del sistema
        $userCountBefore = User::count();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // En tests no hay envío real de correo de verificación, así que se marca directo
        if (app()->environment('testing')) {
            $user->markEmailAsVerified();
        }

        // Bootstrap: solo si la tabla de usuarios estaba vacía y el flag está habilitado en config
        $canBootstrapAdmin = $userCountBefore === 0
            && (bool) config('auth.first_user_admin_enabled', false);

        if ($canBootstrapAdmin) {
            Role::firstOrCreate([
                'name' => 'administrador',
                'guard_name' => 'web',
            ]);

            $user->assignRole('administrador');
        } else {
            Role::firstOrCreate([
                'name' => 'cliente',
                'guard_name' => 'web',
            ]);

            $user->assignRole('cliente');
            // Todo usuario con rol cliente necesita su perfil Client con preferencias de notificación por defecto
            Client::firstOrCreate([
                'user_id' => $user->id,
            ], [
                'preferencias_notificacion' => [
                    'in_app' => true,
                    'email' => true,
                    'sms' => false,
                    'whatsapp' => false,
                ],
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
