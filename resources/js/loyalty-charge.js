/**
 * Calculo compartido de "cuanto se cobra realmente" cuando hay descuento de
 * nivel de lealtad y/o canje de puntos de por medio. Antes vivia duplicado
 * (con las mismas formulas escritas dos veces) en el x-data de
 * payments/create.blade.php y en cobroModal() de appointments/index.blade.php
 * — un solo lugar evita que las dos vistas se desincronicen si cambia la
 * regla de negocio (1 punto = $1 MXN, tope 50% del total con nivel aplicado).
 *
 * Esto es solo para que el staff VEA el desglose antes de cobrar; el backend
 * (LoyaltyService + PaymentService) vuelve a calcular todo de forma
 * autoritativa al procesar el pago, asi que este calculo nunca mueve dinero
 * por si mismo.
 */
export function computeLoyaltyCharge({
  monto,
  nivelPct,
  puntosDisponibles,
  puntosCanjear,
  propina,
  usarPremioRifa,
}) {
  const montoNum = parseFloat(monto) || 0;
  const propinaNum = parseFloat(propina) || 0;

  // El premio de rifa cubre el servicio completo: no se combina con
  // descuento de nivel ni puntos (ver PaymentService::create()).
  if (usarPremioRifa) {
    return { montoConNivel: 0, maxPuntosCanjeables: 0, descuentoPuntos: 0, total: propinaNum };
  }

  const montoConNivel = montoNum * (1 - (parseFloat(nivelPct) || 0) / 100);
  const maxPuntosCanjeables = Math.max(
    0,
    Math.min(parseInt(puntosDisponibles) || 0, Math.floor(montoConNivel * 0.5)),
  );
  const descuentoPuntos = Math.min(parseInt(puntosCanjear) || 0, maxPuntosCanjeables);
  const total = Math.max(0, montoConNivel - descuentoPuntos) + propinaNum;

  return { montoConNivel, maxPuntosCanjeables, descuentoPuntos, total };
}
