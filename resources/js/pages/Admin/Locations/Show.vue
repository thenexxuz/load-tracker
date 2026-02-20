<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { onMounted, ref, onUnmounted } from 'vue'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import { route } from 'ziggy-js'
import AdminLayout from '@/layouts/AppLayout.vue'
import NotesSection from '@/components/NotesSection.vue'
import { Notify } from 'notiflix'

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
    country: string | null
    latitude: number | null
    longitude: number | null
    recyclingLocation?: { id: number; short_code: string } | null
  }
  mapbox_token: string
}>()

const mapContainer = ref<HTMLDivElement | null>(null)
let map: mapboxgl.Map | null = null

onMounted(() => {
  if (!mapContainer.value || !props.location.latitude || !props.location.longitude) {
    console.warn('Cannot initialize map: missing container or coordinates')
    return
  }

  mapboxgl.accessToken = props.mapbox_token

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/streets-v12',
    center: [props.location.longitude, props.location.latitude],
    zoom: 14,
    attributionControl: true,
  })

  map.addControl(new mapboxgl.NavigationControl(), 'top-right')

  map.on('load', () => {
    // Add marker for the location
    new mapboxgl.Marker({ color: '#ef4444' })
      .setLngLat([props.location.longitude, props.location.latitude])
      .setPopup(
        new mapboxgl.Popup().setHTML(`
          <strong>${props.location.short_code}</strong><br>
          ${props.location.name || 'Unnamed'}<br>
          ${props.location.address || 'No address'}<br>
          ${[props.location.city, props.location.state, props.location.zip].filter(Boolean).join(', ')}
        `)
      )
      .addTo(map!)

    // Optional: Center and zoom to the marker
    map!.flyTo({
      center: [props.location.longitude, props.location.latitude],
      zoom: 15,
      duration: 1500,
    })
  })
})

onUnmounted(() => {
  map?.remove()
})

const page = usePage()

onMounted(() => {
  if (page.props.flash?.success) {
    Notify.success(page.props.flash.success)
  }
  if (page.props.flash?.error) {
    Notify.failure(page.props.flash.error)
  }
  if (page.props.flash?.info) {
    Notify.info(page.props.flash.info)
  }
  if (page.props.flash?.warning) {
    Notify.warning(page.props.flash.warning)
  }
})
</script>

<template>
  <Head :title="`Location: ${location.short_code}`" />

  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Location: {{ location.short_code }}
        </h1>

        <div class="space-x-4">
          <Link
            :href="route('admin.locations.edit', location.id)"
            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
          >
            Edit
          </Link>
          <Link
            :href="route('admin.locations.index')"
            class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
          >
            Back to List
          </Link>
        </div>
      </div>

      <!-- Location Details -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow border dark:border-gray-700 overflow-hidden">
        <div class="p-6">
          <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Short Code</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ location.short_code }}</dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ location.name || '—' }}</dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100 capitalize">
                {{ location.type.replace('_', ' ') }}
              </dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                {{ location.address || '—' }}
              </dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">City / State / Zip</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                {{ [location.city, location.state, location.zip].filter(Boolean).join(', ') || '—' }}
              </dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Country</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                {{ location.country || '—' }}
              </dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Latitude</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                {{ location.latitude?.toFixed(6) ?? '—' }}
              </dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Longitude</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                {{ location.longitude?.toFixed(6) ?? '—' }}
              </dd>
            </div>

            <div v-if="location.recyclingLocation">
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Associated Recycling</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                <Link
                  :href="route('admin.locations.show', location.recyclingLocation.id)"
                  class="text-blue-600 hover:underline dark:text-blue-400"
                >
                  {{ location.recyclingLocation.short_code }}
                </Link>
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Map Section -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow border dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b dark:border-gray-700">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            Location Map
          </h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Showing the precise position of {{ location.short_code }}
          </p>
        </div>

        <div class="h-[500px] relative">
          <div ref="mapContainer" class="absolute inset-0"></div>

          <!-- Fallback if no coordinates -->
          <div
            v-if="!location.latitude || !location.longitude"
            class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400"
          >
            No coordinates available for this location
          </div>
        </div>
      </div>

      <NotesSection
        :entity="location"
        entity-type="App\Models\Location"
        entity-prop-key="location"
      />
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
