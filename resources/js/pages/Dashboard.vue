<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  CategoryScale,
} from 'chart.js'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  CategoryScale
)

const props = defineProps<{
  isAdminOrSupervisor?: boolean
  chartData?: {
    labels: string[]
    values: number[]
  }
  bookedCount?: number
}>()

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top' },
    title: { display: true, text: 'Deliveries per Day (Last 30 Days)' }
  },
  scales: {
    y: { beginAtZero: true }
  }
}

const chartDataComputed = computed(() => ({
  labels: props.chartData?.labels || [],
  datasets: [
    {
      label: 'Delivered Shipments',
      borderColor: '#3b82f6',
      backgroundColor: 'rgba(59, 130, 246, 0.2)',
      data: props.chartData?.values || [],
      tension: 0.3,
      fill: true
    }
  ]
}))
</script>

<template>
  <Head title="Dashboard" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-8">
        Dashboard
      </h1>

      <div v-if="isAdminOrSupervisor" class="space-y-8">
        <!-- Booked Shipments Card -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">
            Booked Shipments
          </h3>
          <p class="text-4xl font-bold text-blue-600 dark:text-blue-400">
            {{ bookedCount ?? 0 }}
          </p>
        </div>

        <!-- Line Chart -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700">
          <div class="h-80">
            <Line
              :data="chartDataComputed"
              :options="chartOptions"
            />
          </div>
        </div>
      </div>

      <!-- Regular dashboard content for non-admin/supervisor users -->
      <div v-else class="text-center py-12 text-gray-600 dark:text-gray-400">
        <p class="text-xl">Welcome to the Dashboard</p>
        <p class="mt-2">Additional statistics are available for administrators and supervisors.</p>
      </div>
    </div>
  </AdminLayout>
</template>
