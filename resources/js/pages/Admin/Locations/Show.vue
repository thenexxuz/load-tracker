<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { format } from 'date-fns'
import mapboxgl from 'mapbox-gl'
import { computed, ref, onMounted, onUnmounted, watch } from 'vue'

import ActionIconButton from '@/components/ActionIconButton.vue'
import AdminLayout from '@/layouts/AppLayout.vue'
import 'mapbox-gl/dist/mapbox-gl.css'

const props = defineProps<{
  location: {
    id: string
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
      id: string
      short_code: string
      name: string | null
      latitude?: number | null
      longitude?: number | null
    } | null
    created_at: string
    updated_at: string
  }
  shipments?: Array<{
    id: string
    shipment_number: string
    bol: string | null
    status: string
    consolidation_number: string | null
    carrier_id: string | null
    carrier_name: string | null
    trailer_id: number | null
    trailer_number: string | null
    loaned_from_trailer_id: number | null
    created_at: string
    updated_at: string
  }>
  carriers?: Array<{
    id: string
    name: string
    is_active: boolean
  }>
  trailers?: Array<{
    id: number
    number: string
    type: string | null
    carrier_id: string | null
    carrier_name: string | null
  }>
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

// Modal state for quick edit
const showEditModal = ref(false)
const selectedShipment = ref<NonNullable<typeof props.shipments>[number] | null>(null)
const editForm = ref({
  carrier_id: null as string | null,
  trailer_id: null as number | null,
  loaned_from_trailer_id: null as number | null,
})
const trailerSearchInput = ref('')
const isSubmitting = ref(false)
const editError = ref<string | null>(null)
const hoveredConsolidationNumber = ref<string | null>(null)

const setHoveredConsolidationNumber = (consolidationNumber: string | null): void => {
  hoveredConsolidationNumber.value = consolidationNumber
}

const clearHoveredConsolidationNumber = (): void => {
  hoveredConsolidationNumber.value = null
}

const isHoveredConsolidation = (consolidationNumber: string | null): boolean => {
  return Boolean(consolidationNumber) && hoveredConsolidationNumber.value === consolidationNumber
}

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

const filteredShipments = computed(() => {
  if (!props.shipments) return []
  // Filter out delivered and cancelled shipments
  return props.shipments.filter(shipment => 
    shipment.status !== 'delivered' && shipment.status !== 'cancelled'
  )
})

const carrierTrailers = computed(() => {
  if (!props.trailers || !editForm.value.carrier_id) return []
  // Filter trailers by selected carrier, exclude those currently on loan
  return props.trailers.filter(t => 
    t.carrier_id === editForm.value.carrier_id && 
    !onLoanTrailers.value.has(t.id)
  )
})

const onLoanTrailers = computed(() => {
  if (!props.shipments) return new Set<number>()
  // Get all trailers that are currently on loan (used by a different carrier)
  const loanedTrailerIds = new Set<number>()
  props.shipments.forEach(shipment => {
    if (shipment.carrier_id && shipment.trailer_id) {
      const trailer = props.trailers?.find(t => t.id === shipment.trailer_id)
      if (trailer && trailer.carrier_id && trailer.carrier_id !== shipment.carrier_id) {
        loanedTrailerIds.add(shipment.trailer_id)
      }
    }
  })
  return loanedTrailerIds
})

const filteredTrailers = computed(() => {
  if (!trailerSearchInput.value) return carrierTrailers.value
  
  const searchLower = trailerSearchInput.value.toLowerCase()
  return carrierTrailers.value.filter(t =>
    t.number.toLowerCase().includes(searchLower)
  )
})

const loanedFromCarrierId = ref<string | null>(null)

const loanedFromCarrierTrailers = computed(() => {
  if (!loanedFromCarrierId.value || !props.trailers) return []
  
  return props.trailers.filter(t => t.carrier_id === loanedFromCarrierId.value && !onLoanTrailers.value.has(t.id))
})

const getLoanedTrailerCarrier = (shipment: Exclude<typeof props.shipments, undefined>[0] | undefined) => {
  if (!shipment?.loaned_from_trailer_id || !props.trailers) return null
  const loanedTrailer = props.trailers.find(t => t.id === shipment.loaned_from_trailer_id)
  return loanedTrailer?.carrier_name || null
}

const openEditModal = (shipment: typeof selectedShipment.value) => {
  selectedShipment.value = shipment
  editForm.value = {
    carrier_id: shipment?.carrier_id || null,
    trailer_id: shipment?.trailer_id || null,
    loaned_from_trailer_id: shipment?.loaned_from_trailer_id || null,
  }
  trailerSearchInput.value = shipment?.trailer_number || ''
  loanedFromCarrierId.value = null
  editError.value = null
  showEditModal.value = true
}

const closeEditModal = () => {
  showEditModal.value = false
  selectedShipment.value = null
  editForm.value = {
    carrier_id: null,
    trailer_id: null,
    loaned_from_trailer_id: null,
  }
  trailerSearchInput.value = ''
  loanedFromCarrierId.value = null
  editError.value = null
}

const submitEdit = async () => {
  if (!selectedShipment.value) return

  isSubmitting.value = true
  editError.value = null

  try {
    const response = await fetch(
      route('admin.shipments.quick-update', selectedShipment.value.id),
      {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
        },
        body: JSON.stringify({
          ...editForm.value,
          trailer_number: trailerSearchInput.value.trim() || null,
        }),
      }
    )

    if (!response.ok) {
      const error = await response.json()
      editError.value = error.message || 'Failed to update shipment'
      return
    }

    closeEditModal()
    // Reload page to reflect changes
    router.visit(window.location.href)
  } catch (error) {
    editError.value = 'An error occurred while updating the shipment'
    console.error(error)
  } finally {
    isSubmitting.value = false
  }
}

watch(
  [trailerSearchInput, () => editForm.value.carrier_id],
  ([nextTrailerNumber]) => {
    const normalizedTrailerNumber = nextTrailerNumber.trim().toLowerCase()

    if (!normalizedTrailerNumber) {
      editForm.value.trailer_id = null
      return
    }

    const matchingTrailer = carrierTrailers.value.find(
      (trailer) => trailer.number.toLowerCase() === normalizedTrailerNumber
    )

    editForm.value.trailer_id = matchingTrailer?.id ?? null
  }
)

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
    <div class="p-6">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Location: {{ location.short_code }}
          <span v-if="location.name" class="text-gray-600 dark:text-gray-400 ml-2 text-xl">
            ({{ location.name }})
          </span>
        </h1>
        <div class="flex items-center gap-4">
          <ActionIconButton
            action="edit"
            :href="route('admin.locations.edit', location.id)"
            title="Edit Location"
          />
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

      <!-- Pickup Shipments Section -->
      <div v-if="shipments && filteredShipments.length > 0" class="mt-8">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
          Pickup Shipments ({{ filteredShipments.length }})
        </h2>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Shipment #
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    BOL
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Carrier
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Trailer
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr
                  v-for="shipment in filteredShipments"
                  :key="shipment.id"
                  :class="[
                    'transition-colors',
                    shipment.consolidation_number && isHoveredConsolidation(shipment.consolidation_number)
                      ? 'bg-amber-100 hover:bg-amber-100 dark:bg-amber-900/35 dark:hover:bg-amber-900/35'
                      : shipment.consolidation_number
                        ? 'bg-amber-50/60 hover:bg-amber-100/70 dark:bg-amber-900/15 dark:hover:bg-amber-900/30'
                      : 'hover:bg-gray-50 dark:hover:bg-gray-700/50',
                  ]"
                  @mouseenter="setHoveredConsolidationNumber(shipment.consolidation_number)"
                  @mouseleave="clearHoveredConsolidationNumber"
                >
                  <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ shipment.shipment_number }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                    {{ shipment.bol || '—' }}
                  </td>
                  <td class="px-4 py-3 text-sm">
                    <span
                      :class="{
                        'inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': shipment.status === 'pending',
                        'inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': shipment.status === 'in_transit',
                        'inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': shipment.status === 'delivered',
                        'inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': shipment.status === 'cancelled',
                      }"
                    >
                      {{ shipment.status.replace('_', ' ') }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                    {{ shipment.carrier_name || '—' }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                    <div>{{ shipment.trailer_number || '—' }}</div>
                    <div v-if="shipment.loaned_from_trailer_id" class="text-xs text-amber-600 dark:text-amber-400 font-semibold">
                      (on loan from {{ getLoanedTrailerCarrier(shipment) }})
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm">
                    <button
                      @click="openEditModal(shipment)"
                      class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium"
                    >
                      Edit
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- No Shipments Message -->
      <div v-else-if="shipments" class="mt-8">
        <div class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 text-center">
          <p class="text-gray-600 dark:text-gray-400">
            No shipments found with this location as a pickup point.
          </p>
        </div>
      </div>
    </div>

    <!-- Quick Edit Modal -->
  <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden">
    <!-- Background overlay -->
    <div
      class="absolute inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity"
      @click="closeEditModal"
    />

    <!-- Modal panel -->
    <div class="relative z-10 inline-block align-middle bg-white dark:bg-gray-800 rounded-lg shadow-xl transform transition-all w-full mx-4 sm:max-w-md">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Edit Shipment: {{ selectedShipment?.shipment_number }}
          </h3>
        </div>

        <div class="px-6 py-4 space-y-4">
          <!-- Error message -->
          <div v-if="editError" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-300">
            {{ editError }}
          </div>

          <!-- Carrier select -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Carrier
            </label>
            <select
              v-model="editForm.carrier_id"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null">— Select Carrier —</option>
              <option v-for="carrier in carriers" :key="carrier.id" :value="carrier.id">
                {{ carrier.name }}
              </option>
            </select>
          </div>

          <!-- Trailer select -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Trailer
            </label>
            <div class="relative">
              <input
                v-model="trailerSearchInput"
                type="text"
                placeholder="Type trailer number or select from list..."
                :disabled="!editForm.carrier_id"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
              />
              <!-- Dropdown of available trailers -->
              <div
                v-if="editForm.carrier_id && filteredTrailers.length > 0 && trailerSearchInput"
                class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg"
              >
                <div class="max-h-48 overflow-y-auto">
                  <button
                    v-for="trailer in filteredTrailers"
                    :key="trailer.id"
                    type="button"
                    @click="() => {
                      editForm.trailer_id = trailer.id
                      trailerSearchInput = trailer.number
                    }"
                    class="w-full px-3 py-2 text-left text-sm text-gray-900 dark:text-gray-100 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors"
                  >
                    {{ trailer.number }}
                  </button>
                </div>
              </div>
              <!-- Message when no carrier selected -->
              <div v-if="!editForm.carrier_id" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Select a carrier first
              </div>
              <!-- Message when no matching trailers -->
              <div v-else-if="filteredTrailers.length === 0 && trailerSearchInput" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                No trailers found for "{{ trailerSearchInput }}". This trailer will be created when you save.
              </div>
            </div>
          </div>

          <!-- Borrow trailer from another carrier -->
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Borrow From Carrier (optional)
              </label>
              <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                Select a carrier to borrow a trailer from
              </p>
              <select
                v-model="loanedFromCarrierId"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option :value="null">-- Select Carrier --</option>
                <option
                  v-for="carrier in carriers"
                  :key="carrier.id"
                  :value="carrier.id"
                  :disabled="carrier.id === editForm.carrier_id"
                >
                  {{ carrier.name }}
                </option>
              </select>
            </div>

            <div v-if="loanedFromCarrierId">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Select Trailer to Loan
              </label>
              <select
                v-model.number="editForm.loaned_from_trailer_id"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option :value="null">-- Select Trailer --</option>
                <option
                  v-for="trailer in loanedFromCarrierTrailers"
                  :key="trailer.id"
                  :value="trailer.id"
                >
                  {{ trailer.number }}
                </option>
              </select>
              <p v-if="loanedFromCarrierTrailers.length === 0" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                No available trailers for this carrier
              </p>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
          <button
            @click="closeEditModal"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 font-medium transition-colors"
          >
            Cancel
          </button>
          <button
            @click="submitEdit"
            :disabled="isSubmitting"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 text-white font-medium rounded-lg transition-colors"
          >
            {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
