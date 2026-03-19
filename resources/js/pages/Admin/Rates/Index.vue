<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { Confirm, Notify } from 'notiflix'
import { format } from 'date-fns' // optional: better date formatting (npm install date-fns)

const props = defineProps<{
  rates: {
    data: Array<{
      id: number
      name: string | null
      type: 'flat' | 'per_mile'
      rate: number
      pickup_location?: { short_code: string; name?: string | null }
      destination_city: string | null
      destination_state: string | null
      destination_country: string | null
      carrier?: { name: string; short_code?: string }
      effective_from: string | null
      effective_to: string | null
      created_at: string
    }>
    links: any[] // for pagination
    meta: { current_page: number; last_page: number; total: number }
  }
}>()

const deleteRate = (id: number) => {
  Confirm.prompt(
    'Delete Rate',
    'Are you sure you want to delete this rate?',
    'This cannot be undone.',
    'Yes, delete',
    'Cancel',
    () => {
      router.delete(route('admin.rates.destroy', id), {
        onSuccess: () => {
          Notify.success('Rate deleted successfully.')
        },
        onError: () => {
          Notify.failure('Failed to delete rate.')
        },
      })
    },
    () => {},
    {
      titleColor: '#ef4444',
      okButtonBackground: '#ef4444',
    }
  )
}

const formatDate = (date: string | null): string => {
  if (!date) return '—'
  try {
    return format(new Date(date), 'MMM d, yyyy')
  } catch {
    return 'Invalid date'
  }
}

const isActive = (from: string | null, to: string | null): boolean => {
  const now = new Date()
  const start = from ? new Date(from) : null
  const end = to ? new Date(to) : null

  if (start && start > now) return false
  if (end && end < now) return false
  return true
}
</script>

<template>
  <Head title="Rates" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Freight Rates
        </h1>
        <Link
          :href="route('admin.rates.create')"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
        >
          Create New Rate
        </Link>
      </div>

      <!-- Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Name
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Type
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Rate
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Lane (Pickup → Destination)
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Carrier
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Valid Period
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="rate in rates.data" :key="rate.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ rate.name || 'Unnamed Rate' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 capitalize">
                  {{ rate.type }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  <span v-if="rate.type === 'flat'">
                    ${{ rate.rate.toFixed(2) }} flat
                  </span>
                  <span v-else>
                    ${{ rate.rate.toFixed(2) }}/mi
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                  {{ rate.pickup_location?.short_code || '—' }}
                  →
                  <span v-if="rate.destination_city || rate.destination_state || rate.destination_country">
                    {{ [rate.destination_city, rate.destination_state, rate.destination_country].filter(Boolean).join(', ') }}
                  </span>
                  <span v-else>—</span>
                  <div v-if="rate.pickup_location?.name" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ rate.pickup_location.name }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  {{ rate.carrier?.name || '—' }}
                  <span v-if="rate.carrier?.short_code" class="text-xs text-gray-500 dark:text-gray-400">
                    ({{ rate.carrier.short_code }})
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                  <div>
                    From: {{ formatDate(rate.effective_from) }}
                  </div>
                  <div>
                    To: {{ formatDate(rate.effective_to) || 'No end date' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    v-if="isActive(rate.effective_from, rate.effective_to)"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                  >
                    Active
                  </span>
                  <span
                    v-else
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
                  >
                    Inactive
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <Link
                    :href="route('admin.rates.edit', rate.id)"
                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3"
                  >
                    Edit
                  </Link>
                  <button
                    @click="deleteRate(rate.id)"
                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                  >
                    Delete
                  </button>
                </td>
              </tr>

              <tr v-if="rates.data.length === 0">
                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                  No rates found. Create one to get started.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination (simple example) -->
        <div v-if="rates?.meta?.last_page > 1" class="px-6 py-4 flex items-center justify-between border-t dark:border-gray-700">
          <div v-if="rates && rates?.meta && rates?.meta?.last_page > 1">
            Showing page {{ rates?.meta?.current_page }} of {{ rates?.meta?.last_page }} ...
          </div>



          <div v-if="rates?.meta?.last_page > 1" class="...">
            <Link
              v-for="link in rates.links"
              :key="link.url"
              :href="link.url"
              v-html="link.label"
              class="px-3 py-1 rounded-md text-sm"
              :class="{
                'bg-blue-600 text-white': link.active,
                'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600': !link.active && link.url,
                'text-gray-400 cursor-not-allowed': !link.url
              }"
              v-if="link.url || link.active"
            />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>