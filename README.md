# BarberPro Elite - Premium Grooming Management Suite

[![Laravel v12](https://img.shields.io/badge/Laravel-v12-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![TailwindCSS v4](https://img.shields.io/badge/TailwindCSS-v4-06B6D4?style=flat-square&logo=tailwindcss)](https://tailwindcss.com)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-v3-8BC0D0?style=flat-square&logo=alpine.js)](https://alpinejs.dev)
[![Gemini AI](https://img.shields.io/badge/Gemini_AI-Enabled-4285F4?style=flat-square&logo=google-gemini)](https://deepmind.google/technologies/gemini/)

**BarberPro Elite** es un ecosistema digital de alta gama diseñado para revolucionar la gestión de barberías de lujo. Combina una estética visual sofisticada con herramientas operativas de última generación, inteligencia artificial y una experiencia social integrada.

## ✨ Características Principales

### 💎 Diseño Premium "Dark Mode"
- **UI de Lujo:** Interfaz refinada basada en una paleta de Negros Profundos, Dorados Metálicos e Indigo Vibrante.
- **Micro-interacciones:** Animaciones fluidas, efectos de cristal esmerilado (glassmorphism) y resplandores dinámicos.
- **Mobile-First:** Optimización total para dispositivos móviles, garantizando una gestión fluida desde cualquier lugar.

### 🤖 BarberPro Concierge (IA Asistente)
- **Cerebro Gemini:** Integración con la API de Google Gemini para respuestas naturales y contextuales.
- **Modo Híbrido:** Lógica RAG (Retrieval-Augmented Generation) que consulta la base de datos real antes de responder.
- **Fallback Robusto:** Sistema de conocimiento manual offline para asegurar disponibilidad total sin internet.

### 📅 Sistema de Reservas de Élite
- **Calendario Maestro:** Visualización de disponibilidad a 30 días con bloqueo automático de días festivos y descansos.
- **Slots Inteligentes:** Generación dinámica de horarios basada en la duración real de cada servicio y la agenda del barbero.
- **Gestión Individual:** Cada barbero controla su propia jornada laboral, entradas, salidas y días de descanso.

### 📸 Ecosistema Social & Portafolio
- **Muro de Inspiración:** Feed vertical tipo Instagram para descubrir las últimas tendencias de los maestros.
- **Interacciones Reales:** Sistema de Likes, Comentarios y Guardado de estilos para fidelización de clientes.
- **Portafolio Digital:** Vitrina profesional para cada barbero con galerías de trabajos en alta resolución.

### 🏢 Gestión Administrativa & Facturación
- **Dashboard Multiprofil:** Paneles especializados para Admin, Recepcionista, Barbero y Cliente.
- **Facturación Pro:** Emisión automática de comprobantes de pago en PDF con diseño corporativo "Executive".
- **Almacén Central:** Control de stock crítico con soporte de imágenes y trazabilidad de movimientos.
- **Centro de Reportes:** Inteligencia de negocio con exportación de datos a PDF y Excel con estilos profesionales.

### 🚀 Herramientas de Productividad
- **Paleta de Comandos (Ctrl + K):** Navegación ultra-rápida y ejecución de acciones mediante teclado.
- **Modo Mantenimiento Profesional:** Sistema de "Actualización en Progreso" con visualización técnica de despliegue.
- **Notificaciones Premium:** Sistema de Toasts flotantes y correos electrónicos con identidad visual unificada.

## 🛠️ Stack Tecnológico

- **Backend:** Laravel 12 (PHP 8.4)
- **Frontend:** TailwindCSS 4, Alpine.js, Blade Components
- **Asset Manager:** Vite
- **Base de Datos:** MongoDB (Atlas), vía `mongodb/laravel-mongodb`
- **IA:** Google Gemini API
- **Documentos:** DomPDF & Maatwebsite Excel
- **Auditoría:** Spatie Activity Log & Laravel Permissions

## 📦 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/tu-usuario/barberpro-elite.git
   cd barberpro-elite
   ```

2. **Instalar dependencias:**
   ```bash
   composer install
   npm install
   ```

3. **Configuración de entorno:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Base de Datos:**
   Configura `MONGODB_URI` en `.env` apuntando a tu cluster de MongoDB Atlas (o instancia local), luego:
   ```bash
   php artisan migrate --seed
   ```

5. **Activar el "Cerebro de IA" (Opcional):**
   Añade tu clave en el `.env`:
   ```env
   GEMINI_API_KEY=tu_clave_de_google_gemini
   ```

6. **Compilar activos:**
   ```bash
   npm run build
   ```

## 🔐 Credenciales Demo (Seed)

- **Administrador:** `admin@barberia.local` / `password`
- **Recepcionista:** `recepcion@example.com` / `password`
- **Barbero/Cliente:** Ver listado generado en `php artisan tinker`

---


## 🐳 Docker

### Levantar el entorno con Docker

1. Copia el archivo `.env.docker` sobre `.env`:
   ```sh
   cp .env.docker .env
   ```
2. Construye y levanta los contenedores:
   ```sh
   docker-compose up --build
   ```
3. Instala las dependencias de Composer dentro del contenedor:
   ```sh
   docker-compose exec app composer install
   ```
4. Ejecuta las migraciones:
   ```sh
   docker-compose exec app php artisan migrate
   ```

La aplicación estará disponible en [http://localhost:8080](http://localhost:8080)

---

Desarrollado con ❤️ para la excelencia en el Grooming Masculino.  
**© 2026 BarberPro Elite Grooming Studio.**
