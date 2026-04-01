<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
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

        // Count users before creating a new one
        $userCountBefore = User::count();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Mark email as verified for testing
        if (app()->environment('testing')) {
            $user->markEmailAsVerified();
        }

        // Si es el primer usuario registrado (no incluir seeders), asignar rol administrador
        if ($userCountBefore === 0) {
            Role::firstOrCreate([
                'name' => 'administrador',
                'guard_name' => 'web',
            ]);
            $user->assignRole('administrador');
        } else {
            // Todos los demás son clientes
            Role::firstOrCreate([
                'name' => 'cliente',
                'guard_name' => 'web',
            ]);
            $user->assignRole('cliente');
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
