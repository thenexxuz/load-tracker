<script setup lang="ts">
import { Head, router, usePage, useForm } from '@inertiajs/vue3'
import { computed, onUnmounted, onMounted, ref } from 'vue'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import AdminLayout from '@/layouts/AppLayout.vue'
import NotesSection from '@/components/NotesSection.vue'
import { Notify, Confirm } from 'notiflix' // Note: Confirm is from notiflix, but you used Confirm.show in Carrier

const props = defineProps<{
  shipment: {
    id: number
    shipment_number: string
    bol: string | null
    po_number: string | null
    status: string
    pickup_location: { short_code: string; name: string | null } | null
    dc_location: { short_code: string; name: string | null } | null
    carrier: { name: string; short_code: string; wt_code?: string } | null
    drop_date: string | null
    pickup_date: string | null
    delivery_date: string | null
    rack_qty: number
    load_bar_qty: number
    strap_qty: number
    trailer: string | null
    drayage: boolean
    on_site: boolean
    shipped: boolean
    recycling_sent: boolean
    paperwork_sent: boolean
    delivery_alert_sent: boolean
    created_at: string
    updated_at: string
    notes?: Array<{
      id: number
      title: string | null
      content: string
      is_admin: boolean
      created_at: string
      user?: { name: string } | null
    }>
  }
  route_data: {
    route_coords: number[][] | null
    total_km: number | null
    total_miles: number | null
    duration: string | null
    waypoints: Array<{
      id: number
      short_code: string
      name: string | null
      type: string
      lng: number
      lat: number
    }> | null
  } | null
  mapbox_token: string
}>()

const { shipment, route_data } = props

const mapContainer = ref<HTMLDivElement | null>(null)
let map: mapboxgl.Map | null = null

onMounted(() => {
  if (!mapContainer.value || !props.route_data?.route_coords?.length) {
    return
  }

  mapboxgl.accessToken = props.mapbox_token

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/streets-v12',
    center: props.route_data.waypoints?.[0] ? [props.route_data.waypoints[0].lng, props.route_data.waypoints[0].lat] : [0, 0],
    zoom: 8,
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
          coordinates: props.route_data.route_coords,
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
    props.route_data.waypoints?.forEach((wp, index) => {
      const color = index === 0 ? '#22c55e' : index === props.route_data.waypoints.length - 1 ? '#ef4444' : '#f59e0b'

      new mapboxgl.Marker({ color })
        .setLngLat([wp.lng, wp.lat])
        .setPopup(new mapboxgl.Popup().setHTML(`
          <strong>${wp.short_code}</strong> - ${wp.type}<br>
          ${wp.name || 'Unnamed'}<br>
          ${index === 0 ? 'Pickup' : index === props.route_data.waypoints.length - 1 ? 'Final' : 'Stop ' + (index + 1)}
        `))
        .addTo(map!)
    })

    // Fit bounds
    const bounds = new mapboxgl.LngLatBounds()
    props.route_data.route_coords.forEach(([lng, lat]) => bounds.extend([lng, lat]))
    map!.fitBounds(bounds, { padding: 80 })
  })
})

onUnmounted(() => {
  map?.remove()
})

// ── Delete Shipment ─────────────────────────────────────────────────────
const deleteShipment = async () => {
  const result = await Notify.confirm(
    'Delete Shipment',
    'Are you sure you want to delete this shipment? This action cannot be undone.',
    'Yes, delete it',
    'Cancel',
    () => {
      router.delete(route('admin.shipments.destroy', shipment.id), {
        onSuccess: () => {
          Notify.success('Shipment has been deleted.')
          router.visit(route('admin.shipments.index'))
        },
        onError: () => Notify.failure('Failed to delete shipment.'),
      })
    },
    () => {},
    {
      titleColor: '#ff0000',
      okButtonBackground: '#ff0000',
    }
  )
}

// ── Date formatting ─────────────────────────────────────────────────────
const formatDate = (date: string | null, withTime = false) => {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d.getTime())) return '—'

  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const year = d.getFullYear()

  if (!withTime) return `${month}/${day}/${year}`

  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${month}/${day}/${year} ${hours}:${minutes}`
}

// ── Auth & Roles ────────────────────────────────────────────────────────
const { auth } = usePage().props
const userRoles = auth?.user?.roles || []
const hasAdminAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')

</script>

<template>
  <Head title="Shipment Details" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Shipment: {{ shipment.shipment_number }}
        </h1>
        <div class="space-x-4">
          <a :href="route('admin.shipments.edit', shipment.id)"
             class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors">
            Edit
          </a>
          <button @click="deleteShipment"
                  v-if="hasAdminAccess"
                  class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors">
            Delete
          </button>
        </div>
      </div>

      <!-- Shipment Details Card -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 mb-8">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100 capitalize font-medium">{{ shipment.status }}</dd>
          </div>

          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">BOL</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100">
              {{ shipment.bol || '—' }}
            </dd>
            <div v-if="!shipment.bol" class="mt-2">
              <a :href="route('admin.shipments.calculate-bol', shipment.id)">
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors">
                  Calculate BOL
                </button>
              </a>
            </div>
          </div>

          <!-- Carrier fix (added wt_code safe access) -->
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Carrier</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100" v-if="shipment.carrier">
              {{ shipment.carrier.name }} ({{ shipment.carrier.wt_code || 'No WT Code' }})
            </dd>
            <dd v-else class="mt-1 text-gray-900 dark:text-gray-100">
              No carrier assigned
            </dd>
          </div>

          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Trailer</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.trailer || '—' }}</dd>
          </div>

          <!-- Dates – one row, 3 columns -->
          <div class="col-span-full">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Dates</dt>
            <dd class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Drop Date</span>
                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ formatDate(shipment.drop_date) }}</span>
              </div>
              <div>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Pickup Date</span>
                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ formatDate(shipment.pickup_date, true) }}</span>
              </div>
              <div>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Delivery Date</span>
                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ formatDate(shipment.delivery_date, true) }}</span>
              </div>
            </dd>
          </div>

          <!-- Quantities – one row, 3 columns -->
          <div class="col-span-full">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Quantities</dt>
            <dd class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Rack Qty</span>
                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ shipment.rack_qty }}</span>
              </div>
              <div>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Load Bar Qty</span>
                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ shipment.load_bar_qty }}</span>
              </div>
              <div>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Strap Qty</span>
                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ shipment.strap_qty }}</span>
              </div>
            </dd>
          </div>

          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Drayage</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.drayage ? 'Yes' : 'No' }}</dd>
          </div>

          <!-- Flags -->
          <div class="col-span-full">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Flags</dt>
            <dd class="grid grid-cols-2 md:grid-cols-5 gap-4 text-gray-900 dark:text-gray-100">
              <div>On Site: {{ shipment.on_site ? 'Yes' : 'No' }}</div>
              <div>Shipped: {{ shipment.shipped ? 'Yes' : 'No' }}</div>
              <div>Recycling Sent: {{ shipment.recycling_sent ? 'Yes' : 'No' }}</div>
              <div>
                Paperwork Sent: {{ shipment.paperwork_sent ? 'Yes' : 'No' }}<br/>
                <a :href="route('admin.shipments.send-paperwork', shipment.id)" v-if="shipment.carrier && shipment.trailer">
                  <button
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
                  >
                    Send Paperwork
                  </button>
                </a>
              </div>
              <div>Delivery Alert Sent: {{ shipment.delivery_alert_sent ? 'Yes' : 'No' }}</div>
            </dd>
          </div>
        </dl>
      </div>

      <!-- Route Map Section (unchanged) -->
      <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b dark:border-gray-700">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            Route Overview
          </h2>
          <div v-if="route_data" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Total Distance: {{ route_data.total_miles }} mi ({{ route_data.total_km }} km)
            <br>
            Estimated Duration: {{ route_data.duration }}
          </div>
        </div>

        <div class="relative h-[500px]">
          <div ref="mapContainer" class="absolute inset-0"></div>
          <div
            v-if="!route_data"
            class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400"
          >
            No route available (missing coordinates or locations)
          </div>
        </div>
      </div>

      <NotesSection
        :entity="shipment"
        entity-type="App\Models\Shipment"
        entity-prop-key="shipment"
      />

      <!-- Back -->
      <div class="mt-8 text-center">
        <a href="javascript:history.back()" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
          ← Back to Shipments List
        </a>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
:deep(.mapboxgl-map) {
  width: 100%;
  height: 100%;
}
</style>
