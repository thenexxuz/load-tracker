<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { format } from 'date-fns'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'

const props = defineProps<{
  location: {
    id: number
    short_code: string
    name: string | null
    type: string
    address: string | null
    city: string | null
    state: string | null
    zip: string | null
    country: string
    latitude: number | null
    longitude: number | null
    emails: string[] | null
    expected_arrival_time: string | null
    is_active: boolean
    recycling_location?: {
      id: number
      short_code: string
      name: string | null
      latitude?: number | null
      longitude?: number | null
    } | null
    created_at: string
    updated_at: string
  }
  routeData?: {
    distance_km: number
    distance_miles: number
    duration_text: string
    duration_minutes: number
    route_coords: Array<[number, number]>
  } | null
  mapbox_token: string
}>()

const { location } = props

const mapContainer = ref<HTMLDivElement | null>(null)
let map: mapboxgl.Map | null = null

const formatDate = (date: string | null): string => {
  if (!date) return '—'
  try {
    return format(new Date(date), 'MMM d, yyyy')
  } catch {
    return 'Invalid date'
  }
}

const emailsDisplay = computed(() => {
  if (!location.emails?.length) return '—'
  return location.emails.join(', ')
})

onMounted(() => {
  if (!mapContainer.value || !location.latitude || !location.longitude) return

  mapboxgl.accessToken = props.mapbox_token

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/streets-v12',
    center: [location.longitude, location.latitude],
    zoom: 10,
  })

  map.addControl(new mapboxgl.NavigationControl(), 'top-right')

  map.on('load', () => {
    // Add route if available
    if (props.routeData?.route_coords && props.routeData.route_coords.length > 0) {
      map!.addSource('route', {
        type: 'geojson',
        data: {
          type: 'Feature',
          properties: {},
          geometry: {
            type: 'LineString',
            coordinates: props.routeData.route_coords,
          },
        },
      })

      map!.addLayer({
        id: 'route',
        type: 'line',
        source: 'route',
        layout: { 'line-join': 'round', 'line-cap': 'round' },
        paint: { 'line-color': '#3b82f6', 'line-width': 4, 'line-opacity': 0.7 },
      })
    }

    // Add marker for location
    new mapboxgl.Marker({ color: '#22c55e' })
      .setLngLat([location.longitude!, location.latitude!])
      .setPopup(
        new mapboxgl.Popup().setHTML(`
          <strong>${location.short_code}</strong><br>
          ${location.name || 'Unnamed'}<br>
          <span class="text-xs capitalize">${location.type.replace('_', ' ')}</span>
        `)
      )
      .addTo(map!)

    // Add marker for recycling location if available and has coordinates
    if (location.recycling_location?.latitude && location.recycling_location?.longitude) {
      new mapboxgl.Marker({ color: '#ef4444' })
        .setLngLat([location.recycling_location.longitude, location.recycling_location.latitude])
        .setPopup(
          new mapboxgl.Popup().setHTML(`
            <strong>${location.recycling_location.short_code}</strong><br>
            ${location.recycling_location.name || 'Unnamed'}<br>
            <span class="text-xs">Recycling Location</span>
          `)
        )
        .addTo(map!)

      // Fit bounds to both markers (and route if it exists)
      const bounds = new mapboxgl.LngLatBounds()
      bounds.extend([location.longitude!, location.latitude!])
      bounds.extend([location.recycling_location.longitude, location.recycling_location.latitude])

      // If route exists, extend bounds to include all route coordinates
      if (props.routeData?.route_coords && props.routeData.route_coords.length > 0) {
        props.routeData.route_coords.forEach(([lng, lat]) => bounds.extend([lng, lat]))
      }

      map!.fitBounds(bounds, { padding: 80 })
    }
  })
})

onUnmounted(() => {
  map?.remove()
})
</script>

<template>
  <Head title="Location Details" />

  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Location: {{ location.short_code }}
          <span v-if="location.name" class="text-gray-600 dark:text-gray-400 ml-2 text-xl">
            ({{ location.name }})
          </span>
        </h1>
        <div class="space-x-4">
          <Link
            :href="route('admin.locations.edit', location.id)"
            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium"
          >
            Edit Location
          </Link>
          <Link
            :href="route('admin.locations.index')"
            class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
          >
            Back to List
          </Link>
        </div>
      </div>

      <!-- Map -->
      <div v-if="location.latitude && location.longitude" class="mb-6 rounded-lg overflow-hidden shadow-lg border border-gray-200 dark:border-gray-700 h-96">
        <div ref="mapContainer" class="w-full h-full" />
      </div>

      <!-- Recycling grouping note (if applicable) -->
      <div v-if="location.type === 'recycling'" class="mb-6 p-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-lg text-sm text-blue-800 dark:text-blue-200">
        Note: This is a recycling location. Multiple recycling locations can share the same short code for grouping purposes.
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-8">
          <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
            <!-- Short Code -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Short Code</dt>
              <dd class="mt-1.5 text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ location.short_code }}
              </dd>
            </div>

            <!-- Name -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100">
                {{ location.name || '—' }}
              </dd>
            </div>

            <!-- Type -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100 capitalize font-medium">
                {{ location.type.replace('_', ' ') }}
              </dd>
            </div>

            <!-- Active Status -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
              <dd class="mt-1.5">
                <span
                  :class="{
                    'inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': location.is_active,
                    'inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': !location.is_active
                  }"
                >
                  {{ location.is_active ? 'Active' : 'Inactive' }}
                </span>
              </dd>
            </div>

            <!-- Address -->
            <div class="md:col-span-2">
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100 whitespace-pre-line">
                {{ location.address || '—' }}
                <div v-if="location.city || location.state || location.zip" class="mt-1">
                  {{ [location.city, location.state, location.zip].filter(Boolean).join(', ') }}
                </div>
                <div v-if="location.country" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                  {{ location.country }}
                </div>
              </dd>
            </div>

            <!-- Coordinates -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Coordinates</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100">
                <span v-if="location.latitude && location.longitude">
                  Lat: {{ location.latitude.toFixed(6) }}<br />
                  Lng: {{ location.longitude.toFixed(6) }}
                </span>
                <span v-else>—</span>
              </dd>
            </div>

            <!-- Emails -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Emails</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100">
                <div v-if="location.emails?.length" class="flex flex-wrap gap-2">
                  <span
                    v-for="email in location.emails"
                    :key="email"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                  >
                    {{ email }}
                  </span>
                </div>
                <span v-else class="text-gray-500 dark:text-gray-400">— No emails —</span>
              </dd>
            </div>

            <!-- Expected Arrival Time -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expected Arrival Time</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100">
                {{ location.expected_arrival_time || '—' }}
              </dd>
            </div>

            <!-- Recycling Location (if DC) -->
            <div v-if="location.type === 'distribution_center'" class="md:col-span-2">
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Associated Recycling Location</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100">
                <span v-if="location.recycling_location">
                  {{ location.recycling_location.short_code }} - {{ location.recycling_location.name || 'Unnamed' }}
                </span>
                <span v-else>— None —</span>
              </dd>
            </div>

            <!-- Timestamps -->
            <div class="md:col-span-2 border-t dark:border-gray-700 pt-6 mt-4">
              <div class="grid grid-cols-2 gap-8 text-sm text-gray-600 dark:text-gray-400">
                <div>
                  Created: {{ formatDate(location.created_at) }}
                </div>
                <div>
                  Last Updated: {{ formatDate(location.updated_at) }}
                </div>
              </div>
            </div>
          </dl>
        </div>

        <!-- Actions footer -->
        <div class="px-8 py-5 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700 flex justify-end gap-4">
          <Link
            :href="route('admin.locations.index')"
            class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 font-medium"
          >
            Back to Locations
          </Link>
          <Link
            :href="route('admin.locations.edit', location.id)"
            class="inline-flex items-center px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors"
          >
            Edit This Location
          </Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
