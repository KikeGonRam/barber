/**
 * Animación de entrada del hero de la landing pública (welcome.blade.php),
 * con GSAP. No-op si el hero no existe en la página actual (el bundle es
 * global) o si el visitante prefiere movimiento reducido. Si GSAP falla por
 * cualquier razón, el catch fuerza opacidad 1 en los elementos para que el
 * contenido nunca quede invisible.
 */
import gsap from 'gsap';

export function initHeroAnimation() {
  const hero = document.querySelector('header#inicio');
  if (!hero) {
    return;
  }

  const elements = {
    badge: hero.querySelector('[data-hero="badge"]'),
    title: hero.querySelector('[data-hero="title"]'),
    subtitle: hero.querySelector('[data-hero="subtitle"]'),
    desc: hero.querySelector('[data-hero="desc"]'),
    cta: hero.querySelector('[data-hero="cta"]'),
    scroll: hero.querySelector('[data-hero="scroll"]'),
  };

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    return;
  }

  try {
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

    if (elements.badge) {
      tl.from(elements.badge, { opacity: 0, y: -16, duration: 0.6 });
    }
    if (elements.title) {
      tl.from(elements.title, { opacity: 0, y: 40, scale: 0.94, duration: 0.9 }, '-=0.25');
    }
    if (elements.subtitle) {
      tl.from(elements.subtitle, { opacity: 0, y: 24, duration: 0.7 }, '-=0.55');
    }
    if (elements.desc) {
      tl.from(elements.desc, { opacity: 0, y: 18, duration: 0.6 }, '-=0.4');
    }
    if (elements.cta) {
      tl.from(elements.cta.children, { opacity: 0, y: 18, duration: 0.6, stagger: 0.12 }, '-=0.35');
    }
    if (elements.scroll) {
      tl.from(elements.scroll, { opacity: 0, duration: 0.8 }, '-=0.2');
    }
  } catch {
    Object.values(elements).forEach((el) => {
      if (el) {
        el.style.opacity = 1;
      }
    });
  }
}
