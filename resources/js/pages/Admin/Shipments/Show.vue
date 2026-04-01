<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import mapboxgl from 'mapbox-gl'
import { onMounted, onUnmounted, ref, computed } from 'vue'
import { route } from 'ziggy-js'

import 'mapbox-gl/dist/mapbox-gl.css'
import ActionIconButton from '@/components/ActionIconButton.vue'
import NotesSection from '@/components/NotesSection.vue'
import AdminLayout from '@/layouts/AppLayout.vue'

import { Notify } from 'notiflix'

const props = defineProps<{
  shipment: {
    id: string
    shipment_number: string
    bol: string | null
    po_number: string | null
    status: string
    pickup_location: { 
      id: string;
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
      id: string;
      short_code: string; 
      name: string | null; 
      address: string | null;
      city: string | null;
      state: string | null;
      zip: string | null;
      country: string | null;
      latitude?: number | null;
      longitude?: number | null;
      recycling_location_id?: string | null;
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
    pickup_to_dc_miles: number | null
    dc_to_recycling_miles: number | null
    duration: string | null
    waypoints: Array<{
      id: string
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
    carrier: { id: string; name: string; short_code: string; wt_code?: string } | null
    rate_per_mile: number
    effective_date: string | null
    expires_at: string | null
    notes: string | null
    type: string
    name: string | null
    destination_city: string | null
    destination_state: string | null
    destination_country: string | null
    calculation_type: string
  }>
  hasAssignedCarrier: boolean
  availableCarriers: Array<{
    id: string
    name: string
    short_code: string
  }>
  offeredCarriers: Array<{
    id: string
    name: string
    short_code: string
    offered_by_user: {
      id: number
      name: string | null
    } | null
  }>
  canManageConsolidation: boolean
  consolidationData: {
    number: string | null
    members: Array<{
      id: string
      shipment_number: string
      bol: string | null
      rack_qty: number
      load_bar_qty: number
      strap_qty: number
      carrier_id: string | null
      trailer_id: number | null
    }>
    totals: {
      rack_qty: number
      load_bar_qty: number
      strap_qty: number
    }
    eligible_shipments: Array<{
      id: string
      shipment_number: string
      bol: string | null
      carrier_id: string | null
      trailer_id: number | null
    }>
    selected_shipment_ids: string[]
  }
}>()

const { shipment, route_data, rates = [], hasAssignedCarrier, availableCarriers, offeredCarriers, canManageConsolidation, consolidationData } = props

const mapContainer = ref<HTMLDivElement | null>(null)
let map: mapboxgl.Map | null = null
const includedRateIds = ref<number[]>(hasAssignedCarrier ? rates.map((rate) => rate.id) : [])

const isRateIncludedInTotal = (rateId: number): boolean => includedRateIds.value.includes(rateId)

const toggleRateIncludedInTotal = (rateId: number): void => {
  if (includedRateIds.value.includes(rateId)) {
    includedRateIds.value = includedRateIds.value.filter((id) => id !== rateId)
    return
  }

  includedRateIds.value = [...includedRateIds.value, rateId]
}

const calculateRateTotal = (rate: typeof rates[0]): number | null => {
  if (!route_data) return null

  let miles: number | null = null

  switch (rate.calculation_type) {
    case 'pickup_to_dc':
      miles = route_data.pickup_to_dc_miles || null
      break
    case 'dc_to_recycling':
      miles = route_data.dc_to_recycling_miles || null
      break
    case 'full_route':
    default:
      miles = route_data.total_miles || null
      break
  }

  if (miles === null || rate.type !== 'per_mile') return null

  return miles * rate.rate_per_mile
}

const totalRateCost = computed(() => {
  if (includedRateIds.value.length === 0) return null

  const result = rates.reduce((accumulator, rate) => {
    if (!isRateIncludedInTotal(rate.id)) {
      return accumulator
    }

    const rateTotal = calculateRateTotal(rate)
    if (rateTotal !== null) {
      accumulator.total += rateTotal
      accumulator.hasAnyValue = true
      return accumulator
    }

    if (rate.type === 'flat') {
      accumulator.total += rate.rate_per_mile
      accumulator.hasAnyValue = true
      return accumulator
    }

    return accumulator
  }, { total: 0, hasAnyValue: false })

  if (!result.hasAnyValue) {
    return null
  }

  return result.total
})

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

const formatRateDestination = (rate: typeof rates[number]): string => {
  const destinationParts = [rate.destination_city, rate.destination_state, rate.destination_country].filter((part): part is string => Boolean(part && part.trim()))

  if (destinationParts.length === 0) {
    return 'Any destination'
  }

  return destinationParts.join(', ')
}

const { auth } = usePage().props
const userRoles = auth?.user?.roles || []
const hasAdminAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')

const offerForm = useForm({
  offered_carrier_ids: offeredCarriers.map((carrier) => carrier.id),
})

const consolidationForm = useForm({
  consolidated_shipment_ids: [...consolidationData.selected_shipment_ids],
  clear_consolidation: false,
})

const consolidationMembers = computed(() => consolidationData.members ?? [])
const hasConsolidation = computed(() => consolidationMembers.value.length > 1)

const displayedRackQty = computed(() =>
  hasConsolidation.value ? consolidationData.totals.rack_qty : shipment.rack_qty
)
const displayedLoadBarQty = computed(() =>
  hasConsolidation.value ? consolidationData.totals.load_bar_qty : shipment.load_bar_qty
)
const displayedStrapQty = computed(() =>
  hasConsolidation.value ? consolidationData.totals.strap_qty : shipment.strap_qty
)

const submitOfferUpdate = () => {
  offerForm.patch(route('admin.shipments.update-offers', shipment.id), {
    preserveScroll: true,
    onSuccess: () => {
      Notify.success('Shipment offers updated successfully.')
    },
    onError: () => {
      Notify.failure('Failed to update shipment offers.')
    },
  })
}

const submitConsolidationUpdate = () => {
  consolidationForm.clear_consolidation = false
  consolidationForm.patch(route('admin.shipments.update-consolidation', shipment.id), {
    preserveScroll: true,
    onSuccess: () => {
      Notify.success('Shipment consolidation updated successfully.')
    },
    onError: () => {
      Notify.failure('Failed to update shipment consolidation.')
    },
  })
}

const clearConsolidation = () => {
  consolidationForm.clear_consolidation = true
  consolidationForm.patch(route('admin.shipments.update-consolidation', shipment.id), {
    preserveScroll: true,
    onSuccess: () => {
      Notify.success('Shipment unconsolidated successfully.')
    },
    onError: () => {
      Notify.failure('Failed to remove shipment consolidation.')
    },
  })
}
</script>

<template>
  <Head title="Shipment Details" />

  <AdminLayout>
    <div class="p-6 space-y-8">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Shipment: {{ shipment.shipment_number }}
        </h1>
        <div class="flex items-center gap-6">
          <ActionIconButton
            action="edit"
            :href="route('admin.shipments.edit', shipment.id)"
            title="Edit Shipment"
          />
          <ActionIconButton
            v-if="hasAdminAccess"
            action="delete"
            title="Delete Shipment"
            @click="deleteShipment"
          />
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
              <a
                v-if="shipment.pickup_location?.id"
                :href="route('admin.locations.show', shipment.pickup_location.id)"
                class="inline-block font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
              >
                {{ shipment.pickup_location.short_code }}
                <span v-if="shipment.pickup_location.name" class="ml-2 text-gray-600 dark:text-gray-400">
                  ({{ shipment.pickup_location.name }})
                </span>
              </a>
              <div v-else class="font-medium">
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
              <a
                v-if="shipment.dc_location?.id"
                :href="route('admin.locations.show', shipment.dc_location.id)"
                class="inline-block font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
              >
                {{ shipment.dc_location.short_code }}
                <span v-if="shipment.dc_location.name" class="ml-2 text-gray-600 dark:text-gray-400">
                  ({{ shipment.dc_location.name }})
                </span>
              </a>
              <div v-else class="font-medium">
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
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ displayedRackQty }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Load Bar Qty</div>
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ displayedLoadBarQty }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Strap Qty</div>
                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ displayedStrapQty }}</div>
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

      <div
        v-if="hasAdminAccess && !hasAssignedCarrier"
        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700"
      >
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
          Offer Shipment To Carriers
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Offer this shipment to one or more carriers without opening the edit screen.
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submitOfferUpdate">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Offered Carriers
            </label>
            <select
              v-model="offerForm.offered_carrier_ids"
              multiple
              class="w-full min-h-36 p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500 disabled:opacity-60"
            >
              <option v-for="carrier in availableCarriers" :key="carrier.id" :value="carrier.id">
                {{ carrier.short_code }} - {{ carrier.name }}
              </option>
            </select>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
              Carrier users assigned to the selected carriers will see this shipment on their Shipment Index.
            </p>
            <p v-if="offerForm.errors.offered_carrier_ids" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ offerForm.errors.offered_carrier_ids }}
            </p>
            <p v-if="offerForm.errors['offered_carrier_ids.0']" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ offerForm.errors['offered_carrier_ids.0'] }}
            </p>
          </div>

          <div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Currently Offered To</div>
            <div v-if="offeredCarriers.length" class="mt-2 flex flex-wrap gap-2">
              <div
                v-for="carrier in offeredCarriers"
                :key="carrier.id"
                class="rounded-lg bg-blue-100 px-3 py-2 text-xs text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
              >
                <div class="font-medium">
                  {{ carrier.short_code }} - {{ carrier.name }}
                </div>
                <div v-if="carrier.offered_by_user" class="mt-1 text-[11px] text-blue-700 dark:text-blue-300">
                  Offered by {{ carrier.offered_by_user.name ?? 'Unknown User' }}
                </div>
              </div>
            </div>
            <p v-else class="mt-2 text-sm text-gray-500 dark:text-gray-400">
              No carriers are currently offered this shipment.
            </p>
          </div>

          <div class="flex justify-end">
            <button
              type="submit"
              :disabled="offerForm.processing"
              class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md font-medium transition-colors disabled:opacity-60"
            >
              {{ offerForm.processing ? 'Saving...' : 'Save Offers' }}
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Consolidation</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Consolidated shipments share carrier and trailer. Candidates are restricted to the same pickup and DC locations.
            </p>
          </div>
          <div class="text-sm text-gray-600 dark:text-gray-400">
            Number: <strong class="text-gray-900 dark:text-gray-100">{{ consolidationData.number || '—' }}</strong>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Consolidated Shipments</div>
            <div v-if="consolidationMembers.length" class="space-y-2">
              <div
                v-for="member in consolidationMembers"
                :key="member.id"
                class="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2"
              >
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ member.shipment_number }}</div>
                <div class="text-xs text-gray-600 dark:text-gray-400">BOL: {{ member.bol || '—' }}</div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400">No consolidation members.</div>

            <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
              <div class="rounded bg-gray-50 dark:bg-gray-900/40 p-2">
                <div class="text-gray-500 dark:text-gray-400">Rack Qty</div>
                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ consolidationData.totals.rack_qty }}</div>
              </div>
              <div class="rounded bg-gray-50 dark:bg-gray-900/40 p-2">
                <div class="text-gray-500 dark:text-gray-400">Load Bars</div>
                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ consolidationData.totals.load_bar_qty }}</div>
              </div>
              <div class="rounded bg-gray-50 dark:bg-gray-900/40 p-2">
                <div class="text-gray-500 dark:text-gray-400">Straps</div>
                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ consolidationData.totals.strap_qty }}</div>
              </div>
            </div>
          </div>

          <div v-if="canManageConsolidation">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Manage Consolidation</div>
            <select
              v-model="consolidationForm.consolidated_shipment_ids"
              multiple
              class="w-full min-h-44 p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            >
              <option
                v-for="candidate in consolidationData.eligible_shipments"
                :key="candidate.id"
                :value="candidate.id"
              >
                {{ candidate.shipment_number }}{{ candidate.bol ? ` | BOL ${candidate.bol}` : '' }}
              </option>
            </select>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
              Selected shipments will be consolidated with this shipment and inherit this shipment's carrier and trailer.
            </p>
            <p v-if="consolidationForm.errors.consolidated_shipment_ids" class="mt-2 text-sm text-red-600 dark:text-red-400">
              {{ consolidationForm.errors.consolidated_shipment_ids }}
            </p>

            <div class="mt-4 flex items-center justify-end gap-3">
              <button
                type="button"
                :disabled="consolidationForm.processing"
                class="px-4 py-2 rounded-md border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/30 disabled:opacity-60"
                @click="clearConsolidation"
              >
                Unconsolidate
              </button>
              <button
                type="button"
                :disabled="consolidationForm.processing"
                class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md font-medium transition-colors disabled:opacity-60"
                @click="submitConsolidationUpdate"
              >
                {{ consolidationForm.processing ? 'Saving...' : 'Save Consolidation' }}
              </button>
            </div>
          </div>
        </div>
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
          <div v-if="totalRateCost !== null" class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-200 dark:border-blue-800">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                {{ hasAssignedCarrier ? 'Total Rate Cost:' : 'Possible Total Rate Cost:' }}
              </span>
              <span class="text-lg font-bold text-blue-900 dark:text-blue-100">
                ${{ totalRateCost.toFixed(2) }}
              </span>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Include
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Name
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Carrier
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Destination
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Rate per Mile
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Total Cost
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
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  <label class="inline-flex items-center gap-2">
                    <input
                      type="checkbox"
                      :checked="isRateIncludedInTotal(rate.id)"
                      class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      @change="toggleRateIncludedInTotal(rate.id)"
                    >
                    <span class="text-xs text-gray-600 dark:text-gray-400">Use</span>
                  </label>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ rate.name ?? 'Unnamed Rate' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ rate.carrier?.name ?? 'Unknown Carrier' }}
                  <span v-if="rate.carrier?.short_code" class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                    ({{ rate.carrier.short_code }})
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  {{ formatRateDestination(rate) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 font-medium">
                  ${{ rate.rate_per_mile.toFixed(2) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 font-semibold">
                  <span v-if="calculateRateTotal(rate)">
                    ${{ calculateRateTotal(rate)!.toFixed(2) }}
                  </span>
                  <span v-else-if="rate.type === 'flat'">
                    ${{ rate.rate_per_mile.toFixed(2) }} flat
                  </span>
                  <span v-else class="text-gray-500">
                    N/A
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ rate.effective_date ?? '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ rate.expires_at ?? '—' }}
                </td>
              </tr>
              <tr v-if="rates.length === 0">
                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
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
          :href="route('admin.shipments.index')"
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
