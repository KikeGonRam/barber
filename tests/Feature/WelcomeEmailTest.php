<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Correo de bienvenida al crear la cuenta (el envío va en try/catch en AppServiceProvider). */
class WelcomeEmailTest extends TestCase
{
    protected function tearDown(): void
    {
        User::withTrashed()->forceDelete();

        parent::tearDown();
    }

    public function test_a_new_account_gets_the_welcome_email(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'Cliente Nuevo', 'email' => 'bienvenida@test.local', 'password' => 'password']);

        event(new Registered($user));

        Notification::assertSentTo($user, WelcomeNotification::class, function (WelcomeNotification $n, array $channels) use ($user) {
            $mail = $n->toMail($user);

            return $channels === ['mail'] && $mail->subject === 'Bienvenido a UrbanBlade';
        });
    }
}
