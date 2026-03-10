<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import MultiSelect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

const props = defineProps<{
  locations: Array<{ id: number; short_code: string; address: string; type: string }>
  preselected?: string | null
  mapbox_token: string
  default_rate_per_mile?: number
}>()

// Auto-select preloaded IDs on mount
onMounted(() => {
  if (props.preselected) {
    const ids = props.preselected.split(',').map(Number).filter(id => !isNaN(id))
    if (ids.length >= 2) {
      const preselectedItems = props.locations.filter(loc => ids.includes(loc.id))
      if (preselectedItems.length === ids.length) {
        selectedLocations.value = preselectedItems
        nextTick(() => calculateRoute())
      }
    }
  }
})

const selectedLocations = ref<Array<{ id: number; short_code: string; address: string; type?: string }>>([])
const isLoading = ref(false)
const routeData = ref<{
  total_km: number
  total_miles: number
  total_duration: string
  route_coords: number[][]        // [[lng, lat], ...]
  waypoints?: number[][]          // exact [lng, lat] per stop
} | null>(null)
const error = ref<string | null>(null)

const ratePerMile = ref<number>(props.default_rate_per_mile ?? 2.50)

const estimatedRate = computed(() => {
  if (!routeData.value?.total_miles || ratePerMile.value <= 0) {
    return null
  }
  const amount = routeData.value.total_miles * ratePerMile.value
  return amount.toLocaleString('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
})

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
  routeData.value = null // Clear previous result

  router.post(route('admin.locations.multi-route-calculate'), {
    location_ids: selectedLocations.value.map(loc => loc.id),
  }, {
    onSuccess: async (page) => {
      routeData.value = page.props.route_data

      if (routeData.value?.route_coords?.length) {
        // Wait for Vue to render the map container
        await nextTick()
        await nextTick() // extra safety
        if (mapContainer.value) {
          drawMap()
        } else {
          console.warn('Map container still not available after nextTick')
        }
      } else {
        error.value = 'No valid route data returned from server'
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

// Draw map with improved marker placement and safety checks
const drawMap = () => {
  if (!mapContainer.value || !routeData.value?.route_coords?.length) {
    console.warn('Cannot draw map: missing container or route coordinates')
    return
  }

  if (!mapContainer.value.offsetWidth || !mapContainer.value.offsetHeight) {
    console.warn('Map container has zero size — skipping initialization')
    return
  }

  console.log('Drawing route with', routeData.value.route_coords.length, 'coordinates')

  // Safety: Prevent loop by removing last point if it matches first
  const coords = routeData.value.route_coords
  if (coords.length > 2) {
    const first = coords[0]
    const last = coords[coords.length - 1]
    if (Math.abs(first[0] - last[0]) < 0.0001 && Math.abs(first[1] - last[1]) < 0.0001) {
      console.warn('Detected closed loop — removing last point')
      coords.pop()
    }
  }

  mapboxgl.accessToken = props.mapbox_token

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/streets-v12',
    center: coords[0],
    zoom: 8,
    attributionControl: true,
  })

  map.addControl(new mapboxgl.NavigationControl(), 'top-right')

  map.on('load', () => {
    // Route line
    map!.addSource('route', {
      type: 'geojson',
      data: {
        type: 'Feature',
        properties: {},
        geometry: {
          type: 'LineString',
          coordinates: coords,
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

    // Markers
    selectedLocations.value.forEach((loc, index) => {
      let coord = routeData.value.waypoints?.[index] || null

      if (!coord || coord.length !== 2) {
        if (index === 0) {
          coord = coords[0]
        } else if (index === selectedLocations.value.length - 1) {
          coord = coords[coords.length - 1]
        } else {
          const numSegments = selectedLocations.value.length - 1
          const segmentLengthApprox = Math.floor(coords.length / numSegments)
          const pointIndex = Math.min(index * segmentLengthApprox, coords.length - 1)
          coord = coords[pointIndex]
        }
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

    // Fit bounds
    if (coords.length >= 2) {
      const bounds = new mapboxgl.LngLatBounds()
      coords.forEach(([lng, lat]) => {
        if (isFinite(lng) && isFinite(lat)) {
          bounds.extend([lng, lat])
        }
      })

      const sw = bounds.getSouthWest()
      const ne = bounds.getNorthEast()

      const lngDiff = Math.abs(ne.lng - sw.lng)
      const latDiff = Math.abs(ne.lat - sw.lat)

      if (
        isFinite(sw.lng) && isFinite(sw.lat) &&
        isFinite(ne.lng) && isFinite(ne.lat) &&
        lngDiff > 0.00001 && latDiff > 0.00001
      ) {
        map!.fitBounds(bounds, {
          padding: 100,
          duration: 1200,
          maxZoom: 15,
        })
      } else {
        map!.setCenter(coords[0])
        map!.setZoom(10)
      }
    }
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

      <!-- Location Selection -->
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

      <!-- Rate per Mile Input -->
      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Rate per Mile (for estimation)
        </label>
        <div class="flex items-center gap-3">
          <input
            v-model.number="ratePerMile"
            type="number"
            step="0.01"
            min="0.01"
            class="w-40 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="2.50"
          />
          <span class="text-gray-600 dark:text-gray-400">USD / mile</span>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          Typical 2026 dry van spot rates: ~$2.30–$2.80/mi (varies by lane, season, fuel)
        </p>
      </div>

      <!-- Calculate Button -->
      <button
        @click="calculateRoute"
        :disabled="isLoading || selectedLocations.length < 2"
        class="px-6 py-3 bg-blue-600 text-white rounded-md font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors mt-4"
      >
        {{ isLoading ? 'Calculating...' : 'Calculate Route' }}
      </button>

      <!-- Status / Feedback -->
      <div class="min-h-[1.5rem]">
        <p v-if="error" class="text-red-600 dark:text-red-400">
          {{ error }}
        </p>
        <p v-else-if="isLoading" class="text-blue-600 dark:text-blue-400">
          Calculating route...
        </p>
        <p v-else-if="!routeData" class="text-sm text-gray-600 dark:text-gray-400">
          Select at least two locations and click "Calculate Route" to generate the route.
        </p>
      </div>

      <!-- Route Summary + Estimated Rate -->
      <div v-if="routeData" class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow border dark:border-gray-700">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Distance</h3>
            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {{ routeData.total_miles?.toFixed(1) ?? '—' }} mi
              <span class="text-sm text-gray-500 dark:text-gray-400">
                ({{ routeData.total_km?.toFixed(1) ?? '—' }} km)
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
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Number of Stops</h3>
            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {{ selectedLocations.length }}
            </p>
          </div>

          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Estimated Rate</h3>
            <p class="text-xl font-bold text-green-700 dark:text-green-400">
              {{ estimatedRate ?? '—' }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
              @ ${{ ratePerMile.toFixed(2) }}/mi
            </p>
          </div>
        </div>

        <p class="mt-5 text-sm text-gray-500 dark:text-gray-400 italic">
          This is a rough estimate only. Actual freight rates depend on many factors: fuel surcharge, deadhead miles, accessorials, market conditions, equipment type, etc.
        </p>
      </div>

      <!-- Map -->
      <div v-if="routeData" class="mt-6">
        <div ref="mapContainer" class="w-full h-[600px] rounded-lg border border-gray-300 dark:border-gray-700 shadow overflow-hidden"></div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
:deep(.mapboxgl-map) {
  width: 75vw;
  height: 75vh;
}
:deep(.mapboxgl-popup) {
  color: black;
}
</style>