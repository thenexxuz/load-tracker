<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { route } from 'ziggy-js'

defineProps<{
  distances: Array<{
    dc_short_code: string
    rec_short_code: string
    distance_km: number | null
    distance_miles: number | null
    duration_text: string | null
    dc_id?: number
    rec_id?: number
  }>
}>()
</script>

<template>
  <Head title="Recycling Distances" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Recycling Distances
      </h1>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow border dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  DC Short Code
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Recycling Short Code
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Distance (km)
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Distance (miles)
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Estimated Duration
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="(item, index) in distances" :key="index">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <Link
                    v-if="item.dc_id"
                    :href="route('admin.locations.show', item.dc_id)"
                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline transition-colors"
                  >
                    {{ item.dc_short_code }}
                  </Link>
                  <span v-else class="text-gray-900 dark:text-gray-100">
                    {{ item.dc_short_code }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <Link
                    v-if="item.rec_id"
                    :href="route('admin.locations.show', item.rec_id)"
                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline transition-colors"
                  >
                    {{ item.rec_short_code }}
                  </Link>
                  <span v-else class="text-gray-900 dark:text-gray-100">
                    {{ item.rec_short_code }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ item.distance_km ? item.distance_km.toFixed(1) + ' km' : '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ item.distance_miles ? item.distance_miles.toFixed(1) + ' mi' : '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <Link
                        v-if="item.duration_text && item.dc_id && item.rec_id"
                        :href="route('admin.locations.recycling-distance-map', { dc_id: item.dc_id, rec_id: item.rec_id })"
                        class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline transition-colors"
                    >
                        {{ item.duration_text || '—' }}
                    </Link>
                    <span v-else>
                        {{ item.duration_text || '—' }}
                    </span>
                </td>
              </tr>

              <tr v-if="!distances.length">
                <td colspan="5" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                  No DC / Recycling pairs found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
