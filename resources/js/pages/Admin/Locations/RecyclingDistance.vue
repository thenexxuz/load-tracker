<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { ref, watch } from 'vue'

const props = defineProps<{
  distances: {
    data: Array<{
      dc_id: number
      dc_short_code: string
      rec_id: number | null
      rec_short_code: string | null
      distance_km: number | null
      distance_miles: number | null
      duration_text: string | null
      route_coords: number[][]
    }>
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  recycling_locations: Array<{ id: number; short_code: string }> // ← added from backend
}>()

// Selected recycling filter (null = All, -1 = No Recycling Assigned)
const selectedRecycling = ref<string | number>('all')

// Change page
const changePage = (url: string | null) => {
  if (url) {
    router.visit(url, {
      preserveState: true,
      preserveScroll: true,
    })
  }
}

// Change per page
const changePerPage = (e: Event) => {
  const value = (e.target as HTMLSelectElement).value
  router.get(
    route('admin.locations.recycling-distances'),
    { per_page: value, recycling_id: selectedRecycling.value === 'all' ? null : selectedRecycling.value },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

// Watch filter change → reload with filter param
watch(selectedRecycling, (value) => {
  let recyclingId = null
  if (value === -1) {
    recyclingId = 'none' // special value for no recycling
  } else if (value !== 'all') {
    recyclingId = value
  }

  router.get(
    route('admin.locations.recycling-distances'),
    { recycling_id: recyclingId, page: 1 }, // reset to page 1 on filter change
    { preserveState: true, preserveScroll: true, replace: true }
  )
})
</script>

<template>
  <Head title="Recycling Distances" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Recycling Distances
      </h1>

      <!-- Filters & Per Page -->
      <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <!-- Recycling Filter -->
        <div class="flex items-center space-x-3">
          <label class="text-sm text-gray-700 dark:text-gray-300">Filter by Recycling:</label>
          <select
            v-model="selectedRecycling"
            class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="'all'">All Recycling Locations</option>
            <option :value="-1">No Recycling Assigned</option>
            <option
              v-for="rec in recycling_locations"
              :key="rec.id"
              :value="rec.id"
            >
              {{ rec.short_code }}
            </option>
          </select>
        </div>

        <!-- Per Page -->
        <div class="flex items-center space-x-3">
          <label class="text-sm text-gray-700 dark:text-gray-300">Items per page:</label>
          <select
            @change="changePerPage"
            :value="distances.per_page"
            class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
          </select>
        </div>
      </div>

      <!-- Table -->
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
                  Distance (miles)
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Distance (km)
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Estimated Duration
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="item in distances.data"
                :key="item.dc_id + '-' + (item.rec_id ?? 'none')"
                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
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
                    {{ item.rec_short_code || 'None' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ item.distance_miles ? item.distance_miles.toFixed(1) + ' mi' : '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ item.distance_km ? item.distance_km.toFixed(1) + ' km' : '—' }}
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

              <tr v-if="!distances.data.length">
                <td colspan="5" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                  No DC / Recycling pairs found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="distances.data.length" class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
          <!-- Showing info -->
          <div class="text-sm text-gray-700 dark:text-gray-300 mb-4 sm:mb-0">
            Showing {{ distances.from ?? 0 }}–{{ distances.to ?? 0 }} of {{ distances.total }} entries
          </div>

          <!-- Pagination buttons -->
          <div class="flex flex-wrap items-center gap-1 sm:gap-2">
            <div class="flex flex-wrap items-center gap-1 sm:gap-2">
              <button
                v-for="(link, index) in distances.links"
                :key="index"
                :disabled="!link.url"
                @click="changePage(link.url)"
                class="px-3 py-2 rounded-md text-sm font-medium transition-colors"
                :class="{
                  'bg-blue-600 text-white': link.active,
                  'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700': !link.active && link.url,
                  'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed': !link.url && !link.active
                }"
                v-html="link.label"
              ></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
