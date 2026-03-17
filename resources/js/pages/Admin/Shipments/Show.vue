<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { onMounted, onUnmounted, ref } from 'vue'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import AdminLayout from '@/layouts/AppLayout.vue'
import NotesSection from '@/components/NotesSection.vue'
import { Notify } from 'notiflix'

const props = defineProps<{
  shipment: {
    id: number
    shipment_number: string
    bol: string | null
    po_number: string | null
    status: string
    pickup_location: { 
      short_code: string; 
      name: string | null; 
      address: string | null;
      city: string | null;
      state: string | null;
      zip: string | null;
      country: string | null;
      latitude?: number | null;
      longitude?: number | null;
    } | null
    dc_location: { 
      short_code: string; 
      name: string | null; 
      address: string | null;
      city: string | null;
      state: string | null;
      zip: string | null;
      country: string | null;
      latitude?: number | null;
      longitude?: number | null;
      recycling_location_id?: number | null;
    } | null
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
  rates: Array<{
    id: number
    carrier: { id: number; name: string; short_code: string; wt_code?: string } | null
    rate_per_mile: number
    minimum_charge: number | null
    effective_date: string | null
    expires_at: string | null
    notes: string | null
  }>
  hasAssignedCarrier: boolean
}>()

const { shipment, route_data, rates = [], hasAssignedCarrier } = props

const mapContainer = ref<HTMLDivElement | null>(null)
let map: mapboxgl.Map | null = null

onMounted(() => {
  if (!mapContainer.value || !route_data?.route_coords?.length) return

  mapboxgl.accessToken = props.mapbox_token

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/streets-v12',
    center: route_data.waypoints?.[0] ? [route_data.waypoints[0].lng, route_data.waypoints[0].lat] : [0, 0],
    zoom: 8,
  })

  map.addControl(new mapboxgl.NavigationControl(), 'top-right')

  map.on('load', () => {
    map!.resize()

    map!.addSource('route', {
      type: 'geojson',
      data: {
        type: 'Feature',
        properties: {},
        geometry: {
          type: 'LineString',
          coordinates: route_data.route_coords,
        },
      },
    })

    map!.addLayer({
      id: 'route',
      type: 'line',
      source: 'route',
      layout: { 'line-join': 'round', 'line-cap': 'round' },
      paint: { 'line-color': '#3b82f6', 'line-width': 5, 'line-opacity': 0.8 },
    })

    route_data.waypoints?.forEach((wp, index) => {
      const color = index === 0 ? '#22c55e' : index === route_data.waypoints.length - 1 ? '#ef4444' : '#f59e0b'

      new mapboxgl.Marker({ color })
        .setLngLat([wp.lng, wp.lat])
        .setPopup(new mapboxgl.Popup().setHTML(`
          <strong>${wp.short_code}</strong> - ${wp.type}<br>
          ${wp.name || 'Unnamed'}<br>
          ${index === 0 ? 'Pickup' : index === route_data.waypoints.length - 1 ? 'Final' : 'Stop ' + (index + 1)}
        `))
        .addTo(map!)
    })

    const bounds = new mapboxgl.LngLatBounds()
    route_data.route_coords.forEach(([lng, lat]) => bounds.extend([lng, lat]))
    map!.fitBounds(bounds, { padding: 80 })

    setTimeout(() => map?.resize(), 1200)
  })
})

onUnmounted(() => { map?.remove() })

const deleteShipment = async () => {
  Notify.confirm(
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

// Helper to build readable address string
const formatLocationAddress = (loc: any) => {
  if (!loc) return 'No location data'

  const parts: string[] = []

  if (loc.address) parts.push(loc.address)
  if (loc.city || loc.state || loc.zip) {
    let line = ''
    if (loc.city) line += loc.city
    if (loc.state) line += (line ? ', ' : '') + loc.state
    if (loc.zip) line += (line ? ' ' : '') + loc.zip
    if (line) parts.push(line)
  }
  if (loc.country) parts.push(loc.country)

  return parts.length > 0 ? parts.join('\n') : 'No address recorded'
}

const { auth } = usePage().props
const userRoles = auth?.user?.roles || []
const hasAdminAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
</script>

<template>
  <Head title="Shipment Details" />

  <AdminLayout>
    <div class="p-6 space-y-8">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Shipment: {{ shipment.shipment_number }}
        </h1>
        <div class="space-x-6">
          <a
            :href="route('admin.shipments.edit', shipment.id)"
            class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
          >
            Edit
          </a>
          <button
            v-if="hasAdminAccess"
            @click="deleteShipment"
            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
          >
            Delete
          </button>
        </div>
      </div>

      <!-- Locations Card -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
          Locations
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <!-- Pickup Location -->
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Pickup Location</dt>
            <dd class="mt-2 space-y-1 text-gray-900 dark:text-gray-100">
              <div class="font-medium">
                {{ shipment.pickup_location?.short_code || '—' }}
                <span v-if="shipment.pickup_location?.name" class="ml-2 text-gray-600 dark:text-gray-400">
                  ({{ shipment.pickup_location.name }})
                </span>
              </div>
              <div class="text-sm whitespace-pre-line leading-relaxed">
                {{ formatLocationAddress(shipment.pickup_location) }}
              </div>
            </dd>
          </div>

          <!-- Distribution Center -->
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Distribution Center (DC)</dt>
            <dd class="mt-2 space-y-1 text-gray-900 dark:text-gray-100">
              <div class="font-medium">
                {{ shipment.dc_location?.short_code || '—' }}
                <span v-if="shipment.dc_location?.name" class="ml-2 text-gray-600 dark:text-gray-400">
                  ({{ shipment.dc_location.name }})
                </span>
              </div>
              <div class="text-sm whitespace-pre-line leading-relaxed">
                {{ formatLocationAddress(shipment.dc_location) }}
              </div>
            </dd>
          </div>
        </div>
      </div>

      <!-- Shipment Details Card -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100 capitalize font-semibold">{{ shipment.status }}</dd>
          </div>

          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">BOL</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100">
              {{ shipment.bol || '—' }}
              <div v-if="!shipment.bol" class="mt-3">
                <a :href="route('admin.shipments.calculate-bol', shipment.id)">
                  <button class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md font-medium transition-colors">
                    Calculate BOL
                  </button>
                </a>
              </div>
            </dd>
          </div>

          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Carrier</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100">
              <span v-if="shipment.carrier">
                {{ shipment.carrier.name }}
                <span v-if="shipment.carrier.wt_code" class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                  ({{ shipment.carrier.wt_code }})
                </span>
              </span>
              <span v-else>No carrier assigned</span>
            </dd>
          </div>

          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Trailer</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.trailer || '—' }}</dd>
          </div>

          <div class="col-span-full">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Dates</dt>
            <dd class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Drop Date</div>
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ formatDate(shipment.drop_date) }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Pickup Date</div>
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ formatDate(shipment.pickup_date, true) }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Delivery Date</div>
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ formatDate(shipment.delivery_date, true) }}</div>
              </div>
            </dd>
          </div>

          <div class="col-span-full">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Quantities</dt>
            <dd class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Rack Qty</div>
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ shipment.rack_qty }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Load Bar Qty</div>
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ shipment.load_bar_qty }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Strap Qty</div>
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ shipment.strap_qty }}</div>
              </div>
            </dd>
          </div>

          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Drayage</dt>
            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.drayage ? 'Yes' : 'No' }}</dd>
          </div>

          <div class="col-span-full">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Flags</dt>
            <dd class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm text-gray-900 dark:text-gray-100">
              <div>On Site: <strong>{{ shipment.on_site ? 'Yes' : 'No' }}</strong></div>
              <div>Shipped: <strong>{{ shipment.shipped ? 'Yes' : 'No' }}</strong></div>
              <div>Recycling Sent: <strong>{{ shipment.recycling_sent ? 'Yes' : 'No' }}</strong></div>
              <div>
                Paperwork Sent: <strong>{{ shipment.paperwork_sent ? 'Yes' : 'No' }}</strong>
                <div v-if="shipment.carrier && shipment.trailer" class="mt-2">
                  <a :href="route('admin.shipments.send-paperwork', shipment.id)">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                      Send Paperwork
                    </button>
                  </a>
                </div>
              </div>
              <div>Delivery Alert Sent: <strong>{{ shipment.delivery_alert_sent ? 'Yes' : 'No' }}</strong></div>
            </dd>
          </div>
        </dl>
      </div>

      <!-- Route Map -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b dark:border-gray-700">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Route Overview</h2>
          <div v-if="route_data" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Total Distance: {{ route_data.total_miles?.toFixed(1) ?? '—' }} mi 
            ({{ route_data.total_km?.toFixed(1) ?? '—' }} km)<br>
            Estimated Duration: {{ route_data.duration ?? '—' }}
          </div>
        </div>
        <div class="relative h-[500px]">
          <div ref="mapContainer" class="absolute inset-0"></div>
          <div
            v-if="!route_data"
            class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400"
          >
            No route data available
          </div>
        </div>
      </div>

      <!-- Rates Table - Integrated per your request -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b dark:border-gray-700">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            {{ hasAssignedCarrier ? 'Rate for Assigned Carrier' : 'Available Rates' }}
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ shipment.pickup_location?.short_code || '—' }} → {{ shipment.dc_location?.short_code || '—' }}
            <span v-if="route_data?.total_miles" class="ml-2 text-gray-500">
              ({{ route_data.total_miles.toFixed(1) }} miles)
            </span>
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Carrier
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Rate per Mile
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Minimum Charge
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Effective Date
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Expires
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="rate in rates" :key="rate.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ rate.carrier?.name ?? 'Unknown Carrier' }}
                  <span v-if="rate.carrier?.short_code" class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                    ({{ rate.carrier.short_code }})
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 font-medium">
                  ${{ rate.rate_per_mile.toFixed(2) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  {{ rate.minimum_charge ? '$' + Number(rate.minimum_charge).toFixed(2) : '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ rate.effective_date ?? '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ rate.expires_at ?? '—' }}
                </td>
              </tr>
              <tr v-if="rates.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                  No rates found for this lane.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Notes Section -->
      <NotesSection
        :entity="shipment"
        entity-type="App\Models\Shipment"
        entity-prop-key="shipment"
      />

      <div class="text-center mt-10">
        <a
          href="javascript:history.back()"
          class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors"
        >
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
:deep(.mapboxgl-popup) {
  color: black;
}
</style>
