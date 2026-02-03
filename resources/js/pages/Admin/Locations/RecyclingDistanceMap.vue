<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { onMounted, ref, onUnmounted } from 'vue'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'

const props = defineProps<{
  dc: { id: number; short_code: string; address: string }
  rec: { id: number; short_code: string; address: string }
  distance_km: number | null
  distance_miles: number | null
  duration_text: string | null
  route_coords: number[][]  // [[lng, lat], ...]
  mapbox_token: string
  error?: string
}>()

const mapContainer = ref<HTMLDivElement | null>(null)
let map: mapboxgl.Map | null = null

const MAPBOX_TOKEN = props.mapbox_token  // ← replace with your token

onMounted(() => {
  if (!mapContainer.value || !props.route_coords?.length) return

  mapboxgl.accessToken = MAPBOX_TOKEN

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/streets-v12',
    center: props.route_coords[0],  // start at first coord
    zoom: 10,
  })

  // Add navigation controls
  map.addControl(new mapboxgl.NavigationControl(), 'top-right')

  // Wait for map to load before adding route
  map.on('load', () => {
    // Add route source
    map!.addSource('route', {
      type: 'geojson',
      data: {
        type: 'Feature',
        properties: {},
        geometry: {
          type: 'LineString',
          coordinates: props.route_coords,
        },
      },
    })

    // Add route layer
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
        'line-width': 6,
        'line-opacity': 0.85,
      },
    })

    // Add start/end markers
    new mapboxgl.Marker({ color: '#22c55e' })
      .setLngLat(props.route_coords[0])
      .setPopup(new mapboxgl.Popup().setText(`DC: ${props.dc.short_code}`))
      .addTo(map!)

    new mapboxgl.Marker({ color: '#ef4444' })
      .setLngLat(props.route_coords[props.route_coords.length - 1])
      .setPopup(new mapboxgl.Popup().setText(`Recycling: ${props.rec.short_code}`))
      .addTo(map!)

    // Fit map to route bounds
    const bounds = new mapboxgl.LngLatBounds()
    props.route_coords.forEach(([lng, lat]) => bounds.extend([lng, lat]))

    map!.fitBounds(bounds, {
      padding: 80,
      duration: 1200,
    })
  })
})

onUnmounted(() => {
  map?.remove()
})
</script>

<template>
  <Head title="Route: {{ dc.short_code }} → {{ rec.short_code }}" />

  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Route: {{ dc.short_code }} → {{ rec.short_code }}
        </h1>
        <Link
          :href="route('admin.locations.recycling-distances')"
          class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
        >
          ← Back to Distances List
        </Link>
      </div>

      <!-- Summary -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow border dark:border-gray-700">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Distance</h3>
            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {{ distance_km ? distance_km.toFixed(1) + ' km' : '—' }} 
              <span class="text-sm text-gray-500 dark:text-gray-400">({{ distance_miles ? distance_miles.toFixed(1) + ' mi' : '—' }})</span>
            </p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Estimated Duration</h3>
            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              {{ duration_text || '—' }}
            </p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">From → To</h3>
            <p class="text-lg text-gray-900 dark:text-gray-100">
              {{ dc.short_code }} → {{ rec.short_code }}
            </p>
          </div>
        </div>

        <p v-if="error" class="mt-4 text-red-600 dark:text-red-400">
          {{ error }}
        </p>
      </div>

      <!-- Map -->
      <div ref="mapContainer" class="w-full h-[600px] rounded-lg border border-gray-300 dark:border-gray-700 shadow overflow-hidden"></div>
    </div>
  </AdminLayout>
</template>

<style scoped>
:deep(.mapboxgl-map) {
  width: 100%;
  height: 100%;
}
</style>
