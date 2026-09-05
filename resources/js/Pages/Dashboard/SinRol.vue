<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout>
    <section class="rounded-2xl border border-ink/[0.06] bg-card p-8">
      <h3 class="text-sm font-black text-ink uppercase tracking-widest">Panel no disponible</h3>
      <p class="mt-2 text-xs text-ink/50">
        Tu perfil no tiene un rol configurado. Contacta a un administrador.
      </p>
    </section>
  </AppLayout>
</template>
