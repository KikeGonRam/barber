<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PromotionNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendPromotionCommand extends Command
{
    protected $signature = 'promo:send
        {titulo : Asunto/titulo de la promocion}
        {cuerpo : Texto del mensaje}
        {--url= : URL del boton (por defecto, catalogo de servicios)}
        {--cta=Reservar ahora : Etiqueta del boton}';

    protected $description = 'Difunde una promocion por correo/in-app a los clientes con opt-in de marketing';

    public function handle(): int
    {
        $titulo = (string) $this->argument('titulo');
        $cuerpo = (string) $this->argument('cuerpo');
        $url = $this->option('url') ?: null;
        $cta = (string) $this->option('cta');

        $notification = new PromotionNotification($titulo, $cuerpo, $cta, $url);

        $enviados = 0;

        // Solo clientes; el via() de la notificacion filtra a quien opto por no
        // recibir promociones. Se procesa por lotes para no cargar todo en memoria.
        User::whereRoleName('cliente')->chunk(200, function ($clients) use ($notification, &$enviados) {
            Notification::send($clients, $notification);
            $enviados += $clients->count();
        });

        $this->info("Promocion encolada para {$enviados} cliente(s) (los que optaron por no recibir se omiten automaticamente).");

        return self::SUCCESS;
    }
}
