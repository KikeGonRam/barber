<script setup>
/**
 * Puerto de resources/views/components/membership-card.blade.php. La
 * interactividad (tilt 3D, flip a QR, contador de puntos animado,
 * celebración al subir de nivel) se reimplementó con estado reactivo de Vue
 * en vez de manipulación directa del DOM — mismo comportamiento visual,
 * misma clave de localStorage ('ub_lvl_rank') para no perder el estado de
 * "ya vio este nivel" de usuarios que ya visitaron la versión Blade.
 * El evento 'celebrate' lo sigue escuchando el script de confetti en
 * resources/views/app.blade.php (no se reimplementa aquí).
 */
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  nivel: { type: String, default: 'nuevo' },
  label: { type: String, default: '' },
  puntos: { type: Number, default: 0 },
  nombre: { type: String, default: '' },
  numero: { type: String, default: '' },
  desde: { type: String, default: '' },
  qr: { type: String, default: null },
  downloadUrl: { type: String, default: null },
});

const TIER_CLASS = {
  nuevo: 'mc-nuevo',
  regular: 'mc-regular',
  vip: 'mc-vip',
  leyenda: 'mc-leyenda',
};
const RANK = { nuevo: 0, regular: 1, vip: 2, leyenda: 3 };

const tierClass = computed(() => TIER_CLASS[props.nivel] ?? 'mc-vip');
const rank = computed(() => RANK[props.nivel] ?? 0);

const isFlipped = ref(false);
const tiltStyle = ref({});
const glareStyle = ref({});
const pointsDisplay = ref(0);

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function onPointerMove(event) {
  if (prefersReducedMotion) return;

  const rect = event.currentTarget.getBoundingClientRect();
  const px = (event.clientX - rect.left) / rect.width;
  const py = (event.clientY - rect.top) / rect.height;
  const max = 9;

  tiltStyle.value = {
    transform: `rotateY(${(px - 0.5) * max * 2}deg) rotateX(${-(py - 0.5) * max * 2}deg) translateY(-3px)`,
  };
  glareStyle.value = { '--mx': `${px * 100}%`, '--my': `${py * 100}%` };
}

function onPointerLeave() {
  tiltStyle.value = {};
}

onMounted(() => {
  if (prefersReducedMotion) {
    pointsDisplay.value = props.puntos;
  } else {
    const step = Math.max(1, Math.ceil(props.puntos / 45));
    const timer = setInterval(() => {
      pointsDisplay.value = Math.min(props.puntos, pointsDisplay.value + step);
      if (pointsDisplay.value >= props.puntos) clearInterval(timer);
    }, 22);
  }

  try {
    const key = 'ub_lvl_rank';
    const seen = localStorage.getItem(key);
    if (seen !== null && rank.value > parseInt(seen, 10)) {
      setTimeout(() => window.dispatchEvent(new CustomEvent('celebrate')), 700);
    }
    localStorage.setItem(key, String(rank.value));
  } catch {
    // localStorage puede fallar en modo privado — la celebración es cosmética, no crítica.
  }
});
</script>

<template>
  <div class="mc-stage">
    <div
      class="mc-tilt"
      :style="tiltStyle"
      @pointermove="onPointerMove"
      @pointerleave="onPointerLeave"
    >
      <div class="mc-flip" :class="{ flipped: isFlipped }">
        <!-- Frente -->
        <div class="mc-face mc-front mc-sweep" :class="tierClass">
          <div v-if="nivel === 'leyenda'" class="mc-holo"></div>
          <div class="mc-noise"></div>
          <div class="mc-glare" :style="glareStyle"></div>
          <div class="mc-inner">
            <div class="mc-row">
              <div class="mc-brand">
                <svg viewBox="0 0 100 116" aria-hidden="true">
                  <path
                    fill="none"
                    stroke="#d4af37"
                    stroke-width="4"
                    d="M50 8 87 24v31c0 24-16 41-37 50C29 96 13 79 13 55V24L50 8Z"
                  />
                  <path fill="#d4af37" d="M30 52h40l-7 6H37zM33 44l10-6 22 3-9 6z" />
                  <circle cx="50" cy="74" r="4.5" fill="#d4af37" />
                </svg>
                <span class="mc-wm">Urban<span>Blade</span></span>
              </div>
              <span class="mc-badge">{{ label }}</span>
            </div>
            <div class="mc-chip"></div>
            <div class="mc-foot">
              <div class="mc-holder">{{ nombre }}</div>
              <div class="mc-num">{{ numero }}</div>
              <div class="mc-meta">
                <div>
                  <div class="mc-lab">Miembro desde</div>
                  <div class="mc-val">{{ desde }}</div>
                </div>
                <div style="text-align: right">
                  <div class="mc-lab">Puntos</div>
                  <div class="mc-val mc-gold">{{ pointsDisplay.toLocaleString() }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Reverso -->
        <div class="mc-face mc-back" :class="tierClass">
          <div class="mc-noise"></div>
          <div class="mc-inner mc-back-inner">
            <div class="mc-back-head">
              <span class="mc-wm">Urban<span>Blade</span></span>
              <span class="mc-badge">{{ label }}</span>
            </div>
            <div class="mc-qr">
              <img v-if="qr" :src="qr" alt="Codigo de socio" />
              <div v-else class="mc-qr-ph">QR</div>
            </div>
            <div class="mc-back-foot">
              <div class="mc-num">{{ numero }}</div>
              <div class="mc-back-hint">Presenta este codigo en recepcion</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mc-actions">
      <button type="button" class="mc-btn" @click="isFlipped = !isFlipped">
        {{ isFlipped ? 'Ver tarjeta' : 'Ver QR de socio' }}
      </button>
      <a v-if="downloadUrl" :href="downloadUrl" target="_blank" rel="noopener" class="mc-btn"
        >Descargar tarjeta</a
      >
    </div>
  </div>
</template>

<style scoped>
.mc-stage {
  perspective: 1200px;
}
.mc-tilt {
  transform-style: preserve-3d;
  transition: transform 0.12s ease;
}
.mc-flip {
  position: relative;
  width: 100%;
  aspect-ratio: 1.586/1;
  transform-style: preserve-3d;
  transition: transform 0.75s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.mc-flip.flipped {
  transform: rotateY(180deg);
}
.mc-face {
  position: absolute;
  inset: 0;
  border-radius: 18px;
  overflow: hidden;
  padding: 22px 24px;
  -webkit-backface-visibility: hidden;
  backface-visibility: hidden;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow:
    0 24px 55px rgba(0, 0, 0, 0.6),
    0 2px 0 rgba(255, 255, 255, 0.05) inset;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}
.mc-back {
  transform: rotateY(180deg);
}
.mc-front::before {
  content: '';
  position: absolute;
  inset: -2px;
  z-index: 2;
  pointer-events: none;
  background: linear-gradient(
    115deg,
    transparent 35%,
    rgba(255, 255, 255, 0.2) 48%,
    transparent 62%
  );
  background-size: 250% 250%;
  background-position: 150% 0;
}
.mc-sweep.mc-front::before {
  animation: mcSweep 6s ease-in-out infinite;
}
@keyframes mcSweep {
  0%,
  62% {
    background-position: 150% 0;
  }
  100% {
    background-position: -50% 0;
  }
}
.mc-glare {
  position: absolute;
  inset: 0;
  z-index: 3;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.2s;
  mix-blend-mode: soft-light;
  background: radial-gradient(
    220px circle at var(--mx, 50%) var(--my, 50%),
    rgba(255, 255, 255, 0.28),
    transparent 60%
  );
}
.mc-front:hover .mc-glare {
  opacity: 1;
}
.mc-noise {
  position: absolute;
  inset: 0;
  z-index: 1;
  opacity: 0.5;
  pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.045'/%3E%3C/svg%3E");
}
.mc-inner {
  position: relative;
  z-index: 4;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.mc-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.mc-brand {
  display: flex;
  align-items: center;
  gap: 8px;
}
.mc-brand svg {
  width: 26px;
  height: 26px;
}
.mc-wm {
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: -0.02em;
  font-size: 15px;
  color: #f4f4f2;
}
.mc-wm span {
  color: #d4af37;
}
.mc-badge {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  padding: 5px 10px;
  border-radius: 999px;
  border: 1px solid currentColor;
}
.mc-chip {
  position: relative;
  width: 38px;
  height: 29px;
  border-radius: 6px;
  margin: 16px 0 0;
  background: linear-gradient(135deg, #e9d9a0, #b9962f);
  box-shadow:
    0 1px 2px rgba(0, 0, 0, 0.4) inset,
    0 1px 1px rgba(255, 255, 255, 0.3);
}
.mc-chip::after {
  content: '';
  position: absolute;
  inset: 5px;
  border-radius: 3px;
  background:
    linear-gradient(
      90deg,
      transparent 46%,
      rgba(0, 0, 0, 0.35) 46%,
      rgba(0, 0, 0, 0.35) 54%,
      transparent 54%
    ),
    linear-gradient(
      0deg,
      transparent 44%,
      rgba(0, 0, 0, 0.35) 44%,
      rgba(0, 0, 0, 0.35) 56%,
      transparent 56%
    );
}
.mc-foot {
  margin-top: auto;
}
.mc-holder {
  font-size: 17px;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  margin-top: 12px;
  color: #fff;
  line-height: 1.15;
}
.mc-num {
  font-family: ui-monospace, 'Roboto Mono', Menlo, Consolas, monospace;
  font-size: 12px;
  letter-spacing: 0.16em;
  color: rgba(255, 255, 255, 0.72);
  margin-top: 3px;
}
.mc-meta {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 11px;
}
.mc-lab {
  font-size: 7.5px;
  font-weight: 800;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.5);
}
.mc-val {
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  font-variant-numeric: tabular-nums;
}
.mc-gold {
  color: #d4af37;
}
.mc-back-inner {
  align-items: center;
  text-align: center;
}
.mc-back-head {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mc-back .mc-wm {
  font-size: 13px;
}
.mc-qr {
  margin: auto;
  background: #fff;
  border-radius: 10px;
  padding: 8px;
  width: 118px;
  height: 118px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.mc-qr img {
  width: 100%;
  height: 100%;
  display: block;
}
.mc-qr-ph {
  font-weight: 900;
  color: #111;
  font-size: 20px;
}
.mc-back-foot {
  margin-top: auto;
  width: 100%;
}
.mc-back-hint {
  font-size: 8px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.45);
  margin-top: 4px;
}
.mc-nuevo {
  background: linear-gradient(145deg, #20222a, #0d0e12);
}
.mc-nuevo .mc-badge {
  color: #c9ccd4;
}
.mc-regular {
  background: linear-gradient(145deg, #182433, #0b1320);
}
.mc-regular .mc-badge {
  color: #7fb0e6;
}
.mc-vip {
  background: linear-gradient(145deg, #2b2415 0%, #171207 55%, #241d0e 100%);
  box-shadow:
    0 24px 55px rgba(0, 0, 0, 0.6),
    0 0 0 1px rgba(212, 175, 55, 0.25) inset,
    0 2px 0 rgba(255, 255, 255, 0.06) inset;
}
.mc-vip .mc-badge {
  color: #d4af37;
}
.mc-leyenda {
  background: #0c0b10;
}
.mc-leyenda .mc-badge {
  color: #f0abfc;
}
.mc-holo {
  position: absolute;
  inset: 0;
  z-index: 1;
  opacity: 0.5;
  pointer-events: none;
  mix-blend-mode: color-dodge;
  background: conic-gradient(from 0deg, #e879f9, #60a5fa, #34d399, #f5d87a, #e879f9);
  background-size: 300% 300%;
  filter: blur(2px) saturate(1.2);
  animation: mcHolo 8s linear infinite;
}
@keyframes mcHolo {
  0% {
    background-position: 0% 50%;
  }
  100% {
    background-position: 300% 50%;
  }
}
.mc-actions {
  display: flex;
  gap: 10px;
  margin-top: 14px;
}
.mc-btn {
  flex: 1;
  text-align: center;
  cursor: pointer;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  padding: 10px 12px;
  border-radius: 11px;
  border: 1px solid rgba(212, 175, 55, 0.3);
  background: rgba(212, 175, 55, 0.05);
  color: #d4af37;
  text-decoration: none;
  transition: all 0.2s;
}
.mc-btn:hover {
  border-color: rgba(212, 175, 55, 0.6);
  background: rgba(212, 175, 55, 0.1);
}
@media (prefers-reduced-motion: reduce) {
  .mc-sweep.mc-front::before,
  .mc-holo,
  .mc-flip {
    animation: none;
    transition: none;
  }
}
</style>
