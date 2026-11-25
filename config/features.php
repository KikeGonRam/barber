<?php

/**
 * Interruptores de funciones (auditoría de funciones, 23-nov-2026, decisión del propietario).
 *
 * El dueño del proyecto decidió apagar estos módulos por ahora: casi no se usaban (ver evidencia de uso en
 * docs/referencia/PRUEBAS_CARGA.md y en el conteo de peticiones de staging) y algunos tenían huecos reales
 * (paquetes no se podían canjear en móvil, tarjetas de regalo sin pantalla de administración). Son interruptores,
 * no borrado: el código y los datos existentes se quedan intactos, y se reactivan con un cambio de variable de
 * entorno sin tocar código. Todos están apagados por defecto a propósito.
 *
 * En `routes/api.php` cada grupo de rutas solo se registra si su interruptor está encendido: con el interruptor
 * apagado la ruta no existe (404), no hace falta un 403 aparte. `CatalogController::barbershop()` expone este
 * mismo arreglo (sin cambios) en `GET /api/v1/barbershop`, el único endpoint público que ya consultan tanto la web
 * como la app móvil al arrancar, para que oculten el menú sin tener que llamar a un endpoint nuevo.
 */
return [
    // Paquetes prepagados de servicios (compra y catálogo). No afecta el canje de paquetes YA comprados
    // (PackageService::redeem() sigue funcionando): solo deja de venderse paquetes nuevos.
    'packages' => (bool) env('FEATURE_PACKAGES', false),

    // Tarjetas de regalo (compra, consulta de saldo). No afecta una tarjeta YA comprada: GiftCardService::apply()
    // sigue aceptándola en el cobro; solo deja de venderse tarjetas nuevas.
    'gift_cards' => (bool) env('FEATURE_GIFT_CARDS', false),

    // Programa de referidos (enlace propio y "quién me refirió").
    'referrals' => (bool) env('FEATURE_REFERRALS', false),

    // Sorteo mensual de fidelización: oculta el panel de administración y apaga el sorteo mensual programado
    // (ver DrawMonthlyRaffle). Un premio YA sorteado y no reclamado se puede seguir canjeando al cobrar
    // (RaffleService::activePrizeFor()/claim() no se tocan): no se cancela nada a medio camino.
    'raffles' => (bool) env('FEATURE_RAFFLES', false),

    // Muro social (publicaciones del portafolio, reacciones, comentarios) y lo que depende solo de él:
    // moderación de comentarios y bloquear/desbloquear autores.
    'social' => (bool) env('FEATURE_SOCIAL', false),

    // Campañas de correo por segmento.
    'campaigns' => (bool) env('FEATURE_CAMPAIGNS', false),
];
