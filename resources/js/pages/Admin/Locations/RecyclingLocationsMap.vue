<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import mapboxgl from 'mapbox-gl'
import { computed, onMounted, onUnmounted, ref } from 'vue'

import 'mapbox-gl/dist/mapbox-gl.css'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  recycling_locations: Array<{
    id: string
    short_code: string
    name: string | null
    address: string | null
    city: string | null
    state: string | null
    zip: string | null
    country: string | null
    latitude: number | null
    longitude: number | null
  }>
  mapbox_token: string
}>()

const mapContainer = ref<HTMLDivElement | null>(null)
let map: mapboxgl.Map | null = null

const locationsWithCoordinates = computed(() => props.recycling_locations.filter((location) => location.latitude !== null && location.longitude !== null))

const formatAddressLine = (location: typeof props.recycling_locations[number]): string => {
  const cityStateZip = [location.city, location.state, location.zip].filter(Boolean).join(', ')
  const parts = [location.address, cityStateZip, location.country].filter(Boolean)

  return parts.length ? parts.join(' | ') : 'No address recorded'
}

onMounted(() => {
  if (!mapContainer.value || !props.mapbox_token || locationsWithCoordinates.value.length === 0) {
    return
  }

  mapboxgl.accessToken = props.mapbox_token

  const firstLocation = locationsWithCoordinates.value[0]

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/streets-v12',
    center: [firstLocation.longitude!, firstLocation.latitude!],
    zoom: 4,
  })

  map.addControl(new mapboxgl.NavigationControl(), 'top-right')

  map.on('load', () => {
    const bounds = new mapboxgl.LngLatBounds()

    locationsWithCoordinates.value.forEach((location) => {
      const lng = location.longitude!
      const lat = location.latitude!

      new mapboxgl.Marker({ color: '#f97316' })
        .setLngLat([lng, lat])
        .setPopup(new mapboxgl.Popup().setHTML(`
          <strong>${location.short_code}</strong><br>
          ${location.name ?? 'Unnamed recycling location'}<br>
          ${formatAddressLine(location)}
        `))
        .addTo(map!)

      bounds.extend([lng, lat])
    })

    map!.fitBounds(bounds, { padding: 80, maxZoom: 10 })
  })
})

onUnmounted(() => {
  map?.remove()
})
</script>

<template>
  <Head title="Recycling Locations Map" />

  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Recycling Locations Map</h1>
        <Link
          :href="route('admin.locations.recycling-distances')"
          class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
        >
          View Recycling Distances
        </Link>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="h-[580px] w-full relative">
            <div ref="mapContainer" class="absolute inset-0"></div>
            <div
              v-if="locationsWithCoordinates.length === 0"
              class="absolute inset-0 flex items-center justify-center text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900"
            >
              No recycling locations with coordinates available.
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
              Recycling Locations ({{ recycling_locations.length }})
            </h2>
          </div>
          <div class="max-h-[580px] overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700">
            <div
              v-for="location in recycling_locations"
              :key="location.id"
              class="px-4 py-3"
            >
              <Link
                :href="route('admin.locations.show', location.id)"
                class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
              >
                {{ location.short_code }}
              </Link>
              <div class="text-sm text-gray-700 dark:text-gray-300">{{ location.name || 'Unnamed recycling location' }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ formatAddressLine(location) }}</div>
              <div class="text-xs mt-1" :class="location.latitude !== null && location.longitude !== null ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400'">
                {{ location.latitude !== null && location.longitude !== null ? 'Coordinates available' : 'Missing coordinates' }}
              </div>
            </div>

            <div v-if="recycling_locations.length === 0" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
              No recycling locations found.
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
:deep(.mapboxgl-map) {
  width: 100%;
  height: 100%;
}

:deep(.mapboxgl-popup) {
  color: black;
}
</style>
