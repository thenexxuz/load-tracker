<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted } from 'vue'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import MultiSelect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

const props = defineProps<{
  locations: Array<{ id: number; short_code: string; address: string; type: string }>
  mapbox_token: string
}>()

const selectedLocations = ref<{ id: number; short_code: string; address: string }[]>([])
const isLoading = ref(false)
const routeData = ref<{
  total_km: number
  total_miles: number
  total_duration: string
  route_coords: number[][]        // full continuous route
  waypoints: number[][]           // exact [lng, lat] for each stop (new!)
} | null>(null)
const error = ref<string | null>(null)

const mapContainer = ref<HTMLDivElement | null>(null)
let map: mapboxgl.Map | null = null

// Submit selected locations to backend
const calculateRoute = async () => {
  if (selectedLocations.value.length < 2) {
    error.value = 'Please select at least two locations'
    return
  }

  isLoading.value = true
  error.value = null

  router.post(route('admin.locations.multi-route-calculate'), {
    location_ids: selectedLocations.value.map(loc => loc.id),
  }, {
    onSuccess: (page) => {
      routeData.value = page.props.route_data
      if (routeData.value?.route_coords?.length) {
        drawMap()
      } else {
        error.value = 'No valid route data returned'
      }
    },
    onError: (errors) => {
      error.value = errors.message || 'Failed to calculate route'
    },
    onFinish: () => {
      isLoading.value = false
    },
  })
}

// Draw map with exact waypoint markers
const drawMap = () => {
  if (!mapContainer.value || !routeData.value?.route_coords?.length) {
    console.warn('No route coordinates available to draw map')
    return
  }

  mapboxgl.accessToken = props.mapbox_token

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/streets-v12',
    center: routeData.value.route_coords[0],
    zoom: 8,
  })

  map.on('load', () => {
    // Route line
    map!.addSource('route', {
      type: 'geojson',
      data: {
        type: 'Feature',
        properties: {},
        geometry: {
          type: 'LineString',
          coordinates: routeData.value.route_coords,
        },
      },
    })

    map!.addLayer({
      id: 'route',
      type: 'line',
      source: 'route',
      layout: {
        'line-join': 'round',
        'line-cap': 'round',
      },
      paint: {
        'line-color': '#3b82f6',
        'line-width': 5,
        'line-opacity': 0.8,
      },
    })

    // Safety: ensure we have waypoints
    if (!routeData.value.waypoints || routeData.value.waypoints.length !== selectedLocations.value.length) {
      console.warn('Waypoints missing or mismatch — falling back to approximation')
    }

    // Place marker for EVERY stop using exact waypoints (preferred) or fallback approximation
    selectedLocations.value.forEach((loc, index) => {
      let coord = routeData.value.waypoints?.[index] || null

      // Fallback if waypoints not provided or invalid
      if (!coord || coord.length !== 2) {
        const totalPoints = routeData.value.route_coords.length
        const pointIndex = index === 0 ? 0 : index === selectedLocations.value.length - 1 ? totalPoints - 1 : Math.round(index * (totalPoints - 1) / (selectedLocations.value.length - 1))
        coord = routeData.value.route_coords[pointIndex] || null
      }

      if (!coord || coord.length !== 2) {
        console.warn(`Skipping marker for stop ${index + 1}: no valid coordinate`)
        return
      }

      const color = index === 0 ? '#22c55e' : index === selectedLocations.value.length - 1 ? '#ef4444' : '#f59e0b'

      new mapboxgl.Marker({ color })
        .setLngLat(new mapboxgl.LngLat(coord[0], coord[1]))
        .setPopup(new mapboxgl.Popup().setText(`${index + 1}: ${loc.short_code}`))
        .addTo(map!)
    })

    // Fit bounds to the full route
    const bounds = new mapboxgl.LngLatBounds()
    routeData.value.route_coords.forEach(([lng, lat]) => bounds.extend([lng, lat]))

    map!.fitBounds(bounds, {
      padding: 100,
      duration: 1200,
      maxZoom: 15,
    })
  })
}

onUnmounted(() => {
  map?.remove()
})
</script>

<template>
  <Head title="Multi-Location Route Planner" />

  <AdminLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
        Multi-Location Route Planner
      </h1>

      <!-- Multi-Select -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Select Locations (2 or more, order matters)
        </label>
        <MultiSelect
          v-model="selectedLocations"
          :options="locations"
          :multiple="true"
          :searchable="true"
          :close-on-select="false"
          label="short_code"
          track-by="id"
          placeholder="Search and select locations..."
          class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
        />
      </div>

      <!-- Calculate Button -->
      <button
        @click="calculateRoute"
        :disabled="isLoading || selectedLocations.length < 2"
        class="px-6 py-3 bg-blue-600 text-white rounded-md font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {{ isLoading ? 'Calculating...' : 'Calculate Route' }}
      </button>

      <!-- Error / Instructions -->
      <p v-if="error" class="mt-4 text-red-600 dark:text-red-400">
        {{ error }}
      </p>
      <p v-else-if="!routeData && !isLoading" class="text-sm text-gray-600 dark:text-gray-400 text-center">
        Select at least two locations and click "Calculate Route" to see the route.
      </p>

      <!-- Summary -->
      <div v-if="routeData" class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow border dark:border-gray-700">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Distance</h3>
            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {{ routeData.total_km?.toFixed(1) ?? '—' }} km
              <span class="text-sm text-gray-500 dark:text-gray-400">
                ({{ routeData.total_miles?.toFixed(1) ?? '—' }} mi)
              </span>
            </p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Duration</h3>
            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {{ routeData.total_duration ?? '—' }}
            </p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Stops</h3>
            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {{ selectedLocations.length }}
            </p>
          </div>
        </div>
      </div>

      <!-- Map -->
      <div ref="mapContainer" class="mt-6 w-full h-[600px] rounded-lg border border-gray-300 dark:border-gray-700 shadow overflow-hidden"></div>
    </div>
  </AdminLayout>
</template>

<style scoped>
:deep(.mapboxgl-map) {
  width: 100%;
  height: 100%;
}
</style>
