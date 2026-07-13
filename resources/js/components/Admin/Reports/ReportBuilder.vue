<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <h3 class="text-2xl font-bold text-gray-900 mb-6">Constructor de Reportes</h3>

    <!-- Plantillas rápidas -->
    <div class="mb-6">
      <h4 class="text-lg font-bold mb-4">Reportes Rápidos</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <button
          @click="loadTemplate('ingresos')"
          class="p-4 bg-green-50 border-2 border-green-500 rounded-lg hover:bg-green-100 transition"
        >
          <p class="text-2xl mb-2"></p>
          <p class="font-bold">Ingresos</p>
          <p class="text-xs text-gray-600 mt-2">Resumen de ingresos por período</p>
        </button>

        <button
          @click="loadTemplate('citas')"
          class="p-4 bg-blue-50 border-2 border-blue-500 rounded-lg hover:bg-blue-100 transition"
        >
          <p class="text-2xl mb-2"></p>
          <p class="font-bold">Citas</p>
          <p class="text-xs text-gray-600 mt-2">Análisis de reservas y ocupación</p>
        </button>

        <button
          @click="loadTemplate('inventario')"
          class="p-4 bg-purple-50 border-2 border-purple-500 rounded-lg hover:bg-purple-100 transition"
        >
          <p class="text-2xl mb-2"></p>
          <p class="font-bold">Inventario</p>
          <p class="text-xs text-gray-600 mt-2">Estado y movimientos de stock</p>
        </button>

        <button
          @click="loadTemplate('clientes')"
          class="p-4 bg-orange-50 border-2 border-orange-500 rounded-lg hover:bg-orange-100 transition"
        >
          <p class="text-2xl mb-2"></p>
          <p class="font-bold">Clientes</p>
          <p class="text-xs text-gray-600 mt-2">Análisis de clientes y segmentos</p>
        </button>
      </div>
    </div>

    <div class="border-t pt-6">
      <!-- Configurador de Reporte -->
      <h4 class="text-lg font-bold mb-4">Personalización</h4>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Configuración -->
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-semibold mb-2">Tipo de Reporte:</label>
            <select v-model="reportConfig.type" class="w-full px-4 py-2 border rounded-lg">
              <option value="ingresos">Ingresos</option>
              <option value="citas">Citas</option>
              <option value="inventario">Inventario</option>
              <option value="clientes">Clientes</option>
              <option value="desempeño">Desempeño de Barberos</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-semibold mb-2">Período:</label>
            <select v-model="reportConfig.period" class="w-full px-4 py-2 border rounded-lg">
              <option value="dia">Hoy</option>
              <option value="semana">Esta Semana</option>
              <option value="mes">Este Mes</option>
              <option value="trimestre">Este Trimestre</option>
              <option value="año">Este Año</option>
              <option value="personalizado">Personalizado</option>
            </select>
          </div>

          <div v-if="reportConfig.period === 'personalizado'" class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold mb-2">Desde:</label>
              <input
                v-model="reportConfig.dateFrom"
                type="date"
                class="w-full px-4 py-2 border rounded-lg"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-2">Hasta:</label>
              <input
                v-model="reportConfig.dateTo"
                type="date"
                class="w-full px-4 py-2 border rounded-lg"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold mb-2">Detalles:</label>
            <select v-model="reportConfig.detail" class="w-full px-4 py-2 border rounded-lg">
              <option value="resumen">Resumen</option>
              <option value="detallado">Detallado</option>
              <option value="muy-detallado">Muy Detallado</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-semibold mb-2">Formato de Exportación:</label>
            <div class="flex gap-2">
              <label class="flex items-center">
                <input v-model="reportConfig.formats" type="checkbox" value="pdf" class="mr-2" />
                <span class="text-sm">PDF</span>
              </label>
              <label class="flex items-center">
                <input v-model="reportConfig.formats" type="checkbox" value="excel" class="mr-2" />
                <span class="text-sm">Excel</span>
              </label>
              <label class="flex items-center">
                <input v-model="reportConfig.formats" type="checkbox" value="csv" class="mr-2" />
                <span class="text-sm">CSV</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Preview -->
        <div class="bg-gray-50 rounded-lg p-4 border-2 border-gray-200">
          <h5 class="font-bold mb-3">Preview</h5>
          <div class="text-sm space-y-2">
            <p><strong>Tipo:</strong> {{ getReportTypeLabel(reportConfig.type) }}</p>
            <p><strong>Período:</strong> {{ getPeriodLabel(reportConfig.period) }}</p>
            <p><strong>Detalle:</strong> {{ reportConfig.detail === 'resumen' ? 'Resumen' : reportConfig.detail === 'detallado' ? 'Detallado' : 'Muy Detallado' }}</p>
            <p><strong>Formatos:</strong> {{ reportConfig.formats.length > 0 ? reportConfig.formats.join(', ').toUpperCase() : 'Ninguno' }}</p>
            <p class="text-gray-600 pt-3">Próximo reporte de <strong>{{ getReportTypeLabel(reportConfig.type) }}</strong> con detalles de <strong>{{ getPeriodLabel(reportConfig.period) }}</strong>.</p>
          </div>
        </div>
      </div>

      <!-- Botones de acción -->
      <div class="flex gap-3 mt-6">
        <button
          @click="generateReport"
          class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-bold"
        >
          Generar Reporte
        </button>
        <button
          @click="scheduleReport"
          class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-bold"
        >
          Programar
        </button>
        <button
          @click="saveTemplate"
          class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition font-bold"
        >
          Guardar Plantilla
        </button>
      </div>
    </div>

    <!-- Reportes guardados -->
    <div class="mt-8 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">Reportes Generados</h4>
      <div class="space-y-3">
        <div
          v-for="report in generatedReports"
          :key="report.id"
          class="bg-gray-50 rounded-lg p-4 flex items-center justify-between border-l-4 border-blue-500"
        >
          <div>
            <p class="font-bold">{{ report.name }}</p>
            <p class="text-sm text-gray-600">{{ report.date }} • {{ report.type }}</p>
          </div>
          <div class="flex gap-2">
            <button
              @click="downloadReport(report.id, 'pdf')"
              class="px-3 py-2 bg-red-500 text-white rounded text-xs hover:bg-red-600"
            >
              PDF
            </button>
            <button
              @click="downloadReport(report.id, 'excel')"
              class="px-3 py-2 bg-green-500 text-white rounded text-xs hover:bg-green-600"
            >
              Excel
            </button>
            <button
              @click="deleteReport(report.id)"
              class="px-3 py-2 bg-gray-500 text-white rounded text-xs hover:bg-gray-600"
            >

            </button>
          </div>
        </div>

        <div v-if="generatedReports.length === 0" class="text-center py-8 text-gray-500">
          No hay reportes generados
        </div>
      </div>
    </div>

    <!-- Plantillas guardadas -->
    <div class="mt-8 pt-6 border-t">
      <h4 class="text-lg font-bold mb-4">Plantillas Personalizadas</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="template in savedTemplates"
          :key="template.id"
          class="bg-blue-50 rounded-lg p-4 border-2 border-blue-300 cursor-pointer hover:bg-blue-100 transition"
          @click="loadTemplate(template.id)"
        >
          <p class="font-bold">{{ template.name }}</p>
          <p class="text-xs text-gray-600 mt-1">{{ template.description }}</p>
          <div class="flex gap-2 mt-3">
            <button
              @click.stop="useTemplate(template.id)"
              class="flex-1 px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700"
            >
              Usar
            </button>
            <button
              @click.stop="deleteTemplate(template.id)"
              class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700"
            >

            </button>
          </div>
        </div>

        <div v-if="savedTemplates.length === 0" class="text-center py-8 text-gray-500 md:col-span-2 lg:col-span-3">
          No hay plantillas guardadas
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ReportBuilder',
  data() {
    return {
      reportConfig: {
        type: 'ingresos',
        period: 'mes',
        detail: 'resumen',
        dateFrom: '2026-04-09',
        dateTo: '2026-05-09',
        formats: ['pdf'],
      },
      generatedReports: [
        { id: 1, name: 'Ingresos Abril 2026', type: 'Ingresos', date: '2026-05-01' },
        { id: 2, name: 'Análisis de Citas Abril', type: 'Citas', date: '2026-04-30' },
        { id: 3, name: 'Estado de Inventario Marzo', type: 'Inventario', date: '2026-03-31' },
      ],
      savedTemplates: [
        { id: 'template-1', name: 'Reporte Mensual', description: 'Ingresos y citas del mes' },
        { id: 'template-2', name: 'Análisis Semanal', description: 'Resumen de la semana' },
        { id: 'template-3', name: 'Inventario Crítico', description: 'Productos con stock bajo' },
      ],
    };
  },
  methods: {
    loadTemplate(templateId) {
      console.log('Load template:', templateId);
      alert('Plantilla cargada');
    },
    generateReport() {
      const report = {
        id: this.generatedReports.length + 1,
        name: `Reporte de ${this.getReportTypeLabel(this.reportConfig.type)}`,
        type: this.getReportTypeLabel(this.reportConfig.type),
        date: new Date().toISOString().split('T')[0],
      };
      this.generatedReports.unshift(report);
      alert('Reporte generado exitosamente');
    },
    scheduleReport() {
      alert('Reporte programado para generar automáticamente');
    },
    saveTemplate() {
      const templateName = prompt('¿Cómo deseas nombrar esta plantilla?');
      if (templateName) {
        this.savedTemplates.push({
          id: 'template-' + Date.now(),
          name: templateName,
          description: `${this.getReportTypeLabel(this.reportConfig.type)} - ${this.getPeriodLabel(this.reportConfig.period)}`,
        });
        alert('Plantilla guardada');
      }
    },
    downloadReport(reportId, format) {
      console.log(`Download report ${reportId} as ${format}`);
      alert(`Descargando reporte en formato ${format.toUpperCase()}`);
    },
    deleteReport(reportId) {
      const index = this.generatedReports.findIndex(r => r.id === reportId);
      if (index > -1) {
        this.generatedReports.splice(index, 1);
        alert('Reporte eliminado');
      }
    },
    useTemplate(templateId) {
      console.log('Use template:', templateId);
      alert('Plantilla aplicada');
    },
    deleteTemplate(templateId) {
      const index = this.savedTemplates.findIndex(t => t.id === templateId);
      if (index > -1) {
        this.savedTemplates.splice(index, 1);
        alert('Plantilla eliminada');
      }
    },
    getReportTypeLabel(type) {
      return {
        ingresos: 'Ingresos',
        citas: 'Citas',
        inventario: 'Inventario',
        clientes: 'Clientes',
        desempeño: 'Desempeño de Barberos',
      }[type] || type;
    },
    getPeriodLabel(period) {
      return {
        dia: 'Hoy',
        semana: 'Esta Semana',
        mes: 'Este Mes',
        trimestre: 'Este Trimestre',
        año: 'Este Año',
        personalizado: 'Personalizado',
      }[period] || period;
    },
  },
};
</script>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
