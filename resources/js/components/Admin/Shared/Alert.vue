<template>
  <div class="flex items-start gap-3 p-4 bg-opacity-50 rounded-lg" :class="alertClasses">
    <span class="text-2xl flex-shrink-0">{{ icon }}</span>
    <div class="flex-1">
      <p class="font-medium">{{ alert.title }}</p>
      <p class="text-sm opacity-90">{{ alert.message }}</p>
      <div v-if="alert.action" class="mt-2 flex gap-2">
        <button
          v-for="btn in alert.action"
          :key="btn.label"
          @click="handleAction(btn.action)"
          class="text-xs px-3 py-1 rounded font-semibold hover:opacity-80 transition"
          :class="btn.style"
        >
          {{ btn.label }}
        </button>
      </div>
    </div>
    <button
      @click="$emit('close')"
      class="text-lg opacity-60 hover:opacity-100 transition flex-shrink-0"
      aria-label="Cerrar"
    >
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
</template>

<script>
export default {
  name: 'Alert',
  props: {
    alert: {
      type: Object,
      required: true,
      validator(obj) {
        return 'type' in obj && 'title' in obj && 'message' in obj;
      },
    },
  },
  computed: {
    alertClasses() {
      const baseClasses = {
        warning: 'bg-yellow-100 text-yellow-900 border-l-4 border-yellow-400',
        error: 'bg-red-100 text-red-900 border-l-4 border-red-400',
        success: 'bg-green-100 text-green-900 border-l-4 border-green-400',
        info: 'bg-blue-100 text-blue-900 border-l-4 border-blue-400',
      };
      return baseClasses[this.alert.type] || baseClasses.info;
    },
    icon() {
      const icons = {
        warning: '',
        error: '',
        success: '',
        info: '',
      };
      return icons[this.alert.type] || '';
    },
  },
  methods: {
    handleAction(action) {
      this.$emit('action', action);
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
