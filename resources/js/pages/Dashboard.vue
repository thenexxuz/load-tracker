<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import {
  Chart as ChartJS,
  Filler,
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  CategoryScale,
} from 'chart.js'
import { computed } from 'vue'
import { Line } from 'vue-chartjs'

import AdminLayout from '@/layouts/AppLayout.vue'

ChartJS.register(
  Filler,
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
  pickupLocationShipmentSummary?: Array<{
    id: number
    name: string
    short_code: string | null
    shipment_count: number
    unassigned_shipment_count: number
    unassigned_shipment_index_url: string
    shipment_index_url: string
    status_breakdown: Array<{
      status: string
      count: number
      shipment_index_url: string
    }>
  }>
  carrierActiveShipmentSummary?: Array<{
    id: number
    name: string
    short_code: string | null
    active_shipment_count: number
    shipment_index_url: string
    status_breakdown: Array<{
      status: string
      count: number
      shipment_index_url: string
    }>
  }>
  offerActivitySummary?: {
    week: {
      start: string
      end: string
      label: string
    }
    users: Array<{
      id: number
      name: string
      offered_shipments_count: number
      assigned_shipments_count: number
    }>
  }
}>()

const formatStatus = (status: string) => status
  .replace(/_/g, ' ')
  .toLowerCase()
  .replace(/\b\w/g, (character) => character.toUpperCase())

const pluralize = (count: number, singular: string, plural = `${singular}s`) =>
  `${count} ${count === 1 ? singular : plural}`

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

        <section class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700 space-y-6">
          <div class="flex flex-col gap-1">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
              Pickup Locations
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Active shipment counts by pickup location, excluding delivered shipments.
            </p>
          </div>

          <div class="grid gap-4 lg:grid-cols-2">
            <div
              v-for="location in pickupLocationShipmentSummary ?? []"
              :key="location.id"
              class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40"
            >
              <Link :href="location.shipment_index_url" class="block transition hover:opacity-90">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                      {{ location.short_code || 'Pickup Location' }}
                    </p>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                      {{ location.name }}
                    </h3>
                  </div>

                  <div class="text-right">
                    <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                      {{ location.shipment_count }}
                    </p>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                      Non-delivered
                    </p>
                  </div>
                </div>
              </Link>

              <div class="mt-2">
                <Link
                  :href="location.unassigned_shipment_index_url"
                  class="text-xs font-medium text-gray-600 underline-offset-2 transition hover:text-blue-600 hover:underline dark:text-gray-300 dark:hover:text-blue-300"
                >
                  Unassigned: {{ location.unassigned_shipment_count }}
                </Link>
              </div>

              <div v-if="location.status_breakdown.length" class="mt-4 flex flex-wrap gap-2">
                <Link
                  v-for="status in location.status_breakdown"
                  :key="`${location.id}-${status.status}`"
                  :href="status.shipment_index_url"
                  class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200 transition hover:bg-blue-50 hover:text-blue-700 hover:ring-blue-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-blue-500/10 dark:hover:text-blue-300 dark:hover:ring-blue-400/30"
                  @click.stop
                >
                  {{ formatStatus(status.status) }}: {{ status.count }}
                </Link>
              </div>

              <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                No active shipments at this pickup location.
              </p>
            </div>
          </div>
        </section>

        <section class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700 space-y-6">
          <div class="flex flex-col gap-1">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
              Active Shipments by Carrier
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Carriers with at least one active (non-delivered, non-cancelled) shipment.
            </p>
          </div>

          <div v-if="carrierActiveShipmentSummary?.length" class="grid gap-4 lg:grid-cols-2">
            <div
              v-for="carrier in carrierActiveShipmentSummary"
              :key="carrier.id"
              class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40"
            >
              <Link :href="carrier.shipment_index_url" class="block transition hover:opacity-90">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                      {{ carrier.short_code || 'Carrier' }}
                    </p>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                      {{ carrier.name }}
                    </h3>
                  </div>

                  <div class="text-right">
                    <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                      {{ carrier.active_shipment_count }}
                    </p>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                      Active
                    </p>
                  </div>
                </div>
              </Link>

              <div class="mt-4 flex flex-wrap gap-2">
                <Link
                  v-for="status in carrier.status_breakdown"
                  :key="`${carrier.id}-${status.status}`"
                  :href="status.shipment_index_url"
                  class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200 transition hover:bg-blue-50 hover:text-blue-700 hover:ring-blue-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-blue-500/10 dark:hover:text-blue-300 dark:hover:ring-blue-400/30"
                  @click.stop
                >
                  {{ formatStatus(status.status) }}: {{ status.count }}
                </Link>
              </div>
            </div>
          </div>

          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            No carriers have active shipments.
          </p>
        </section>

        <section class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700 space-y-6">
          <div class="flex flex-col gap-1">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
              Shipment Offers by User
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Previous calendar week: {{ offerActivitySummary?.week.label ?? 'No range available' }}.
            </p>
          </div>

          <div v-if="offerActivitySummary?.users.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
              <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                  <th class="pb-3 pr-4">User</th>
                  <th class="pb-3 pr-4">Shipments Offered</th>
                  <th class="pb-3">Offered and Assigned</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="offerUser in offerActivitySummary.users" :key="offerUser.id">
                  <td class="py-3 pr-4 font-medium text-gray-900 dark:text-gray-100">
                    {{ offerUser.name }}
                  </td>
                  <td class="py-3 pr-4 text-gray-600 dark:text-gray-300">
                    {{ pluralize(offerUser.offered_shipments_count, 'shipment') }}
                  </td>
                  <td class="py-3 text-gray-600 dark:text-gray-300">
                    {{ pluralize(offerUser.assigned_shipments_count, 'shipment') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            No shipment offers were recorded in the previous calendar week.
          </p>
        </section>
      </div>

      <!-- Regular dashboard content for non-admin/supervisor users -->
      <div v-else class="text-center py-12 text-gray-600 dark:text-gray-400">
        <p class="text-xl">Welcome to the Dashboard</p>
        <p class="mt-2">Additional statistics are available for administrators and supervisors.</p>
      </div>
    </div>
  </AdminLayout>
</template>
