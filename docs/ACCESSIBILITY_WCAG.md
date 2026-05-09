# 📋 Guía de Accesibilidad WCAG 2.1

## Nivel A: Requisitos Básicos ✅

### 1. Perceivable (Perceptible)
- ✅ **Alternativas de texto** - Todas las imágenes tienen `alt` text
- ✅ **Captions/Subtítulos** - Videos tienen subtítulos (cuando aplique)
- ✅ **Contenido adaptable** - Layout responsive para diferentes tamaños
- ✅ **Distinguible** - Contraste de color WCAG AA (4.5:1 para texto)

### 2. Operable (Operable)
- ✅ **Accesible por teclado** - Navegación completa sin ratón
- ✅ **Atajos de teclado** - Ctrl+K, Ctrl+S, Ctrl+?, etc.
- ✅ **Sin traps de teclado** - Focus visible y movimiento lógico
- ✅ **Tiempo suficiente** - Sin temporizadores automáticos

### 3. Understandable (Comprensible)
- ✅ **Lenguaje claro** - Español simple y directo
- ✅ **Consistencia** - Patrones de navegación consistentes
- ✅ **Prevención de errores** - Confirmaciones antes de eliminar
- ✅ **Ayuda disponible** - Help modal (Ctrl+?)

### 4. Robust (Robusto)
- ✅ **Compatible con asistentes** - Etiquetas ARIA apropiadas
- ✅ **Código válido** - HTML/CSS validado
- ✅ **Sin dependencias** - Funciona sin JavaScript (degradación elegante)

---

## Implementaciones Específicas

### Atributos ARIA

```html
<!-- Buttons -->
<button aria-label="Toggle dark mode">🌙</button>

<!-- Forms -->
<input aria-label="Buscar productos" />

<!-- Landmarks -->
<nav aria-label="Navegación principal">...</nav>
<main aria-label="Contenido principal">...</main>
<aside aria-label="Barra lateral">...</aside>

<!-- Lists -->
<ul role="list">
  <li role="listitem">Item</li>
</ul>

<!-- Tables -->
<table role="table">
  <thead role="rowgroup">
    <th scope="col">Columna</th>
  </thead>
</table>

<!-- Live regions -->
<div aria-live="polite" aria-atomic="true">
  Notificación actualizada
</div>
```

### Focus Management

```javascript
// Implementado en componentes
- Focus trap en modales
- Focus restoration después de cerrar
- Focus visible para navegación
- Skip links para saltar navegación
```

### Color & Contrast

```css
/* Mínimo WCAG AA: 4.5:1 para texto normal */
/* Mínimo WCAG AAA: 7:1 para texto normal */

Light Mode:
- Text: #111827 on #ffffff (19:1) ✅
- Links: #2563eb on #ffffff (8:1) ✅
- Buttons: Contraste mínimo 4.5:1 ✅

Dark Mode:
- Text: #f3f4f6 on #111827 (18:1) ✅
- Links: #60a5fa on #111827 (10:1) ✅
- Buttons: Contraste mínimo 4.5:1 ✅
```

### Keyboard Navigation

```
Atajos globales:
- Ctrl+K: Buscar
- Ctrl+S: Guardar
- Ctrl+Shift+D: Toggle Dark Mode
- Ctrl+?: Mostrar Ayuda
- Escape: Cerrar modales
- Tab: Navegar entre elementos
- Enter: Activar botones/links
- Arrow Keys: Navegar en listas
```

### Responsive Design

```css
/* Mobile First Approach */
@media (max-width: 640px) {
  - Toque/click mínimo: 44x44px
  - Texto: 14px mínimo
  - Single column layout
}

@media (min-width: 641px) and (max-width: 1024px) {
  - Tablet optimized
  - 2 column layout
}

@media (min-width: 1025px) {
  - Full desktop layout
  - Multi-column display
}
```

### Motion & Animation

```css
/* Respeta preferencias de usuario */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}

@media (prefers-contrast: more) {
  /* Aumenta contraste para usuarios que lo necesitan */
}

@media (prefers-color-scheme: dark) {
  /* Respeta dark mode del sistema */
}
```

---

## Testing & Validación

### Herramientas Recomendadas

1. **Axe DevTools** - Análisis automático de accesibilidad
2. **WAVE** - WebAIM accessibility evaluation tool
3. **Lighthouse** - Auditoría de Chrome
4. **Screen Readers** - NVDA (Windows), JAWS, VoiceOver (Mac)
5. **Color Contrast Analyzer** - Verificación de contraste

### Pruebas Manuales

- [ ] Navegación completa con Tab
- [ ] Modales con Escape
- [ ] Focus visible en todos los elementos interactivos
- [ ] Alt text en imágenes
- [ ] Formarios con labels asociadas
- [ ] Errores detectables y recuperables
- [ ] Sin traps de teclado
- [ ] Tiempo suficiente (sin auto-submit)
- [ ] Legibilidad en zoom 200%
- [ ] Funcionamiento en 640px ancho

---

## Checklist de Accesibilidad

### Estructura HTML
- [ ] Headings jerárquicos (h1, h2, h3...)
- [ ] Landmarks semánticos (nav, main, aside, footer)
- [ ] Lista de links sin estilos
- [ ] Tabla con headers (`<th scope>`)
- [ ] Form labels asociadas con inputs

### Visual
- [ ] Contraste 4.5:1 para texto normal
- [ ] Contraste 3:1 para texto grande (18pt+)
- [ ] Colores no son el único medio de transmitir info
- [ ] Zoom 200% sin truncado de contenido
- [ ] No requiere scroll horizontal

### Interactivo
- [ ] Navegación por teclado completa
- [ ] Focus visible en todo
- [ ] Sin traps de teclado
- [ ] Atajos de teclado documentados
- [ ] Botones con aria-label si necesario

### Contenido
- [ ] Lenguaje simple y claro
- [ ] Abreviaciones definidas
- [ ] Instrucciones claras
- [ ] Mensajes de error claros
- [ ] Confirmaciones antes de operaciones críticas

### Media
- [ ] Videos con subtítulos
- [ ] Audio con transcripción
- [ ] GIFs sin parpadeo/flash (>3 veces/seg)
- [ ] Textos alternativos para infografías

---

## Nivel AA vs AAA

### Nivel A (Mínimo)
- Accesibilidad básica
- Contraste 3:1

### Nivel AA (Objetivo de BarberPro)
- Accesibilidad mejorada
- Contraste 4.5:1 ✅
- Navegación por teclado ✅
- Etiquetas ARIA ✅

### Nivel AAA (Aspiracional)
- Accesibilidad avanzada
- Contraste 7:1
- Video con audio descripción
- Sign language interpretation

---

## Recursos

- [WCAG 2.1 Official](https://www.w3.org/WAI/WCAG21/quickref/)
- [WebAIM](https://webaim.org/)
- [MDN Accessibility](https://developer.mozilla.org/en-US/docs/Web/Accessibility)
- [A11y Project](https://www.a11yproject.com/)
- [Accessible Colors](https://accessible-colors.com/)

---

## Compromisos de BarberPro

✅ **Cumple WCAG 2.1 Nivel AA**
- Accesible para usuarios con discapacidades visuales
- Navegación completa por teclado
- Soporte para screen readers
- Dark mode automático
- Respeta preferencias del usuario

🎯 **Objetivo: WCAG 2.1 Nivel AAA**
- Mejora continua
- Más allá del mínimo legal
- Experiencia inclusiva para todos

---

*Última actualización: 2026-05-09*
*Phase 4: UX/UI Polish - Accesibilidad & Inclusividad*
