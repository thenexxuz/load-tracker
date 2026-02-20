<script setup lang="ts">
import { Head, router, usePage, useForm } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import { ref, watch, onMounted, computed, nextTick } from 'vue'
import { debounce } from 'lodash' // npm install lodash (if not already present)

import AdminLayout from '@/layouts/AppLayout.vue'
import { Notify } from 'notiflix'

const props = defineProps<{
  shipments: {
    data: Array<{
      id: number
      status: string
      bol: string | null
      shipment_number: string
      pickup_location: { short_code: string; name: string | null } | null
      dc_location: { short_code: string; name: string | null } | null
      drop_date: string | null
      pickup_date: string | null
      delivery_date: string | null
      carrier: { name: string; short_code: string } | null
      trailer: string | null
    }>
    current_page: number
    last_page: number
    from: number
    to: number
    total: number
    per_page: number
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  statuses: string[]
  all_shipper_codes: string[]
  all_dc_codes: string[]
  all_carrier_names: string[]
  filters?: {
    search?: string
    excluded_statuses?: string[]
    excluded_pickup_locations?: string[]
    excluded_dc_locations?: string[]
    excluded_carriers?: string[]
    drop_start?: string
    drop_end?: string
    per_page?: number
  }
}>()

const page = usePage()

// ── Filter state (restore from props.filters if available) ──────────────
const selectedStatuses = ref<string[]>(
  props.filters?.excluded_statuses
    ? props.statuses.filter(s => !props.filters.excluded_statuses!.includes(s))
    : [...props.statuses]
)

const selectedPickupLocations = ref<string[]>(
  props.filters?.excluded_pickup_locations
    ? props.all_shipper_codes.filter(s => !props.filters.excluded_pickup_locations!.includes(s))
    : [...props.all_shipper_codes]
)

const selectedDcLocations = ref<string[]>(
  props.filters?.excluded_dc_locations
    ? props.all_dc_codes.filter(s => !props.filters.excluded_dc_locations!.includes(s))
    : [...props.all_dc_codes]
)

const selectedCarriers = ref<string[]>(
  props.filters?.excluded_carriers
    ? props.all_carrier_names.filter(c => !props.filters.excluded_carriers!.includes(c))
    : [...props.all_carrier_names]
)

const dropStart = ref<string>(props.filters?.drop_start || '')
const dropEnd = ref<string>(props.filters?.drop_end || '')
const search = ref<string>(props.filters?.search || '')

// ── Computed excluded values (for header text) ──────────────────────────
const excludedStatuses = computed(() =>
  props.statuses.filter(s => !selectedStatuses.value.includes(s))
)

const excludedPickupLocations = computed(() =>
  props.all_shipper_codes.filter(s => !selectedPickupLocations.value.includes(s))
)

const excludedDcLocations = computed(() =>
  props.all_dc_codes.filter(s => !selectedDcLocations.value.includes(s))
)

const excludedCarriers = computed(() =>
  props.all_carrier_names.filter(c => !selectedCarriers.value.includes(c))
)

// ── Payload for requests ────────────────────────────────────────────────
const currentFilters = computed(() => ({
  search: search.value.trim() || undefined,
  excluded_statuses: excludedStatuses.value.length ? excludedStatuses.value : undefined,
  excluded_pickup_locations: excludedPickupLocations.value.length ? excludedPickupLocations.value : undefined,
  excluded_dc_locations: excludedDcLocations.value.length ? excludedDcLocations.value : undefined,
  excluded_carriers: excludedCarriers.value.length ? excludedCarriers.value : undefined,
  drop_start: dropStart.value || undefined,
  drop_end: dropEnd.value || undefined,
  per_page: props.shipments.per_page,
}))

// ── Apply filters (POST to same route) ──────────────────────────────────
const applyFilters = debounce(() => {
  router.post(route('admin.shipments.index'), currentFilters.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}, 250) // slight debounce – good for search typing

watch([selectedStatuses, selectedPickupLocations, selectedDcLocations, selectedCarriers, dropStart, dropEnd], applyFilters, { deep: true })
watch(search, applyFilters)

// ── Header dynamic text ─────────────────────────────────────────────────
const statusHeaderText = computed(() => {
  const shown = selectedStatuses.value.length
  const total = props.statuses.length
  if (shown === total) return 'Status'
  if (shown === 0) return 'Status (none)'
  return `Status (${shown}/${total})`
})

const pickupHeaderText = computed(() => {
  const shown = selectedPickupLocations.value.length
  const total = props.all_shipper_codes.length
  if (shown === total) return 'Pickup Location'
  if (shown === 0) return 'Pickup Location (none)'
  return `Pickup Location (${shown}/${total})`
})

const dcHeaderText = computed(() => {
  const shown = selectedDcLocations.value.length
  const total = props.all_dc_codes.length
  if (shown === total) return 'DC'
  if (shown === 0) return 'DC (none)'
  return `DC (${shown}/${total})`
})

const carrierHeaderText = computed(() => {
  const shown = selectedCarriers.value.length
  const total = props.all_carrier_names.length
  if (shown === total) return 'Carrier'
  if (shown === 0) return 'Carrier (none)'
  return `Carrier (${shown}/${total})`
})

const dropDateHeaderText = computed(() => {
  if (!dropStart.value && !dropEnd.value) return 'Drop Date'
  if (dropStart.value && !dropEnd.value)   return `Drop Date from ${dropStart.value}`
  if (!dropStart.value && dropEnd.value)   return `Drop Date to ${dropEnd.value}`
  return `Drop Date ${dropStart.value} – ${dropEnd.value}`
})

const clearDropDate = () => {
  dropStart.value = ''
  dropEnd.value = ''
}

// ── Pagination ──────────────────────────────────────────────────────────
const changePage = (url: string | null) => {
  if (url) {
    router.get(url, currentFilters.value, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
  }
}

const changePerPage = (e: Event) => {
  const value = Number((e.target as HTMLSelectElement).value)
  router.get(route('admin.shipments.index'), {
    ...currentFilters.value,
    per_page: value,
    page: 1, // reset to first page when changing per-page
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

// ── Dropdown visibility & positioning ───────────────────────────────────
const showStatusFilter   = ref(false)
const showPickupFilter   = ref(false)
const showDcFilter       = ref(false)
const showCarrierFilter  = ref(false)
const showDropDateFilter = ref(false)

const statusFilterRef    = ref<HTMLElement | null>(null)
const pickupFilterRef    = ref<HTMLElement | null>(null)
const dcFilterRef        = ref<HTMLElement | null>(null)
const carrierFilterRef   = ref<HTMLElement | null>(null)
const dropDateFilterRef  = ref<HTMLElement | null>(null)

const statusDropdownRoot   = ref<HTMLElement | null>(null)
const pickupDropdownRoot   = ref<HTMLElement | null>(null)
const dcDropdownRoot       = ref<HTMLElement | null>(null)
const carrierDropdownRoot  = ref<HTMLElement | null>(null)
const dropDateDropdownRoot = ref<HTMLElement | null>(null)

const statusDropdownStyle   = ref({ top: '0px', left: '0px' })
const pickupDropdownStyle   = ref({ top: '0px', left: '0px' })
const dcDropdownStyle       = ref({ top: '0px', left: '0px' })
const carrierDropdownStyle  = ref({ top: '0px', left: '0px' })
const dropDateDropdownStyle = ref({ top: '0px', left: '0px' })

const updatePosition = (headerRef: any, styleRef: any) => {
  if (!headerRef.value) return
  const rect = headerRef.value.getBoundingClientRect()
  styleRef.value = {
    top: `${rect.bottom + window.scrollY + 8}px`,
    left: `${rect.left + window.scrollX}px`,
  }
}

watch(showStatusFilter,   val => val && nextTick(() => updatePosition(statusFilterRef,   statusDropdownStyle)))
watch(showPickupFilter,   val => val && nextTick(() => updatePosition(pickupFilterRef,   pickupDropdownStyle)))
watch(showDcFilter,       val => val && nextTick(() => updatePosition(dcFilterRef,       dcDropdownStyle)))
watch(showCarrierFilter,  val => val && nextTick(() => updatePosition(carrierFilterRef,  carrierDropdownStyle)))
watch(showDropDateFilter, val => val && nextTick(() => updatePosition(dropDateFilterRef, dropDateDropdownStyle)))

onClickOutside(statusFilterRef,   () => showStatusFilter.value = false,   { ignore: [statusDropdownRoot] })
onClickOutside(pickupFilterRef,   () => showPickupFilter.value = false,   { ignore: [pickupDropdownRoot] })
onClickOutside(dcFilterRef,       () => showDcFilter.value = false,       { ignore: [dcDropdownRoot] })
onClickOutside(carrierFilterRef,  () => showCarrierFilter.value = false,  { ignore: [carrierDropdownRoot] })
onClickOutside(dropDateFilterRef, () => showDropDateFilter.value = false, { ignore: [dropDateDropdownRoot] })

const toggleStatusFilter = () => {
  showStatusFilter.value = !showStatusFilter.value
  if (showStatusFilter.value) {
    showPickupFilter.value = showDcFilter.value = showCarrierFilter.value = showDropDateFilter.value = false
  }
}

const togglePickupFilter = () => {
  showPickupFilter.value = !showPickupFilter.value
  if (showPickupFilter.value) {
    showStatusFilter.value = showDcFilter.value = showCarrierFilter.value = showDropDateFilter.value = false
  }
}

const toggleDcFilter = () => {
  showDcFilter.value = !showDcFilter.value
  if (showDcFilter.value) {
    showStatusFilter.value = showPickupFilter.value = showCarrierFilter.value = showDropDateFilter.value = false
  }
}

const toggleCarrierFilter = () => {
  showCarrierFilter.value = !showCarrierFilter.value
  if (showCarrierFilter.value) {
    showStatusFilter.value = showPickupFilter.value = showDcFilter.value = showDropDateFilter.value = false
  }
}

const toggleDropDateFilter = () => {
  showDropDateFilter.value = !showDropDateFilter.value
  if (showDropDateFilter.value) {
    showStatusFilter.value = showPickupFilter.value = showDcFilter.value = showCarrierFilter.value = false
  }
}

// ── PBI Import Modal ────────────────────────────────────────────────────
const showPbiImportModal = ref(false)
const selectedPbiFile = ref<File | null>(null)

const pbiImportForm = useForm({
  file: null as File | null,
})

const handlePbiFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  if (input.files?.length) {
    selectedPbiFile.value = input.files[0]
    pbiImportForm.file = input.files[0]
  }
}

const importPbiFile = () => {
  if (!pbiImportForm.file) {
    Notify.failure('Please select a file to import.')
    return
  }

  pbiImportForm.post(route('admin.shipments.pbi-import'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      showPbiImportModal.value = false
      selectedPbiFile.value = null
      pbiImportForm.reset()
      Notify.success('PBI import successful.')
      router.reload({ only: ['shipments'] })
    },
    onError: (errors) => {
      Notify.failure(Object.values(errors).join('<br>') || 'Import failed.')
    }
  })
}

// ── Delete ──────────────────────────────────────────────────────────────
const destroy = async (id: number) => {
  if (!confirm('Are you sure you want to delete this shipment?')) return

  router.delete(route('admin.shipments.destroy', id), {
    onSuccess: () => Notify.success('Shipment deleted.'),
    onError: () => Notify.failure('Failed to delete shipment.'),
  })
}

// ── Helpers ─────────────────────────────────────────────────────────────
const formatDate = (dateString: string | null) => {
  if (!dateString) return '—'
  return dateString.split('T')[0] || '—'
}

const getFullDateTime = (dateString: string | null) => {
  return dateString || 'No date/time recorded'
}

const goToShow = (id: number) => {
  router.visit(route('admin.shipments.show', id))
}

// ── Auth & flash ────────────────────────────────────────────────────────
const { auth } = usePage().props
const userRoles = auth?.user?.roles || []
const hasAdminAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')

onMounted(() => {
  if (page.props.flash?.success) Notify.success(page.props.flash.success)
  if (page.props.flash?.error)   Notify.failure(page.props.flash.error)
})
</script>

<template>
  <Head title="Manage Shipments" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Shipments Management
        </h1>
        <div class="space-x-4">
          <a
            v-if="hasAdminAccess"
            :href="route('admin.shipments.create')"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
          >
            Add New Shipment
          </a>

          <button
            v-if="hasAdminAccess"
            @click="showPbiImportModal = true"
            class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
          >
            Import from PBI XLSX
          </button>
        </div>
      </div>

      <!-- PBI Import Modal -->
      <div v-if="showPbiImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
          <div class="p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">
              Import Shipments from PBI XLSX
            </h2>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
              Upload an Excel (.xlsx) file exported from Power BI.
              First two rows will be ignored; third row should contain headers.
            </p>

            <ul class="list-disc pl-5 mb-6 text-sm text-gray-600 dark:text-gray-400">
              <li>Load → shipment_number</li>
              <li>Status → status</li>
              <li>MSFT PO# → po_number</li>
              <li>Origin → pickup_location short_code</li>
              <li>Destination → dc_location short_code</li>
              <li>Ship Date → pickup_date</li>
              <li>Deliver Date → delivery_date</li>
              <li>Sum of Pallets → rack_qty</li>
            </ul>

            <form @submit.prevent="importPbiFile">
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Select XLSX File
                </label>
                <input
                  type="file"
                  accept=".xlsx,.xls"
                  @change="handlePbiFileChange"
                  class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 dark:file:bg-gray-600 dark:file:text-gray-200"
                  required
                />
              </div>

              <div class="flex justify-end space-x-3">
                <button
                  type="button"
                  @click="showPbiImportModal = false"
                  class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="!selectedPbiFile || pbiImportForm.processing"
                  class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded font-medium disabled:opacity-50"
                >
                  {{ pbiImportForm.processing ? 'Importing...' : 'Import' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Filters & Search -->
      <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
          <input
            v-model="search"
            type="text"
            placeholder="Search by shipment #, BOL or PO..."
            class="w-full border border-gray-300 dark:border-gray-600 rounded p-2.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="flex items-center space-x-3">
          <label class="text-sm text-gray-700 dark:text-gray-300">Per page:</label>
          <select
            @change="changePerPage"
            :value="shipments.per_page"
            class="border border-gray-300 dark:border-gray-600 rounded px-3 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-t-lg shadow">
        <table class="min-w-full bg-white dark:bg-gray-800">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-left">
              <th
                ref="statusFilterRef"
                @click="toggleStatusFilter"
                class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[160px]"
              >
                <div class="flex items-center justify-between select-none">
                  {{ statusHeaderText }}
                  <span class="ml-1 text-xs">▼</span>
                </div>
              </th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">BOL</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Shipment Number</th>
              <th
                ref="pickupFilterRef"
                @click="togglePickupFilter"
                class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[180px]"
              >
                <div class="flex items-center justify-between select-none">
                  {{ pickupHeaderText }}
                  <span class="ml-1 text-xs">▼</span>
                </div>
              </th>
              <th
                ref="dcFilterRef"
                @click="toggleDcFilter"
                class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[140px]"
              >
                <div class="flex items-center justify-between select-none">
                  {{ dcHeaderText }}
                  <span class="ml-1 text-xs">▼</span>
                </div>
              </th>
              <th
                ref="dropDateFilterRef"
                @click="toggleDropDateFilter"
                class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[220px]"
              >
                <div class="flex items-center justify-between select-none">
                  {{ dropDateHeaderText }}
                  <span class="ml-1 text-xs">▼</span>
                </div>
              </th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Pickup Date</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Delivery Date</th>
              <th
                ref="carrierFilterRef"
                @click="toggleCarrierFilter"
                class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[160px]"
              >
                <div class="flex items-center justify-between select-none">
                  {{ carrierHeaderText }}
                  <span class="ml-1 text-xs">▼</span>
                </div>
              </th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Trailer</th>
              <th class="px-6 py-4 font-medium text-center text-gray-700 dark:text-gray-300">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="shipment in shipments.data"
              :key="shipment.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
              @click="goToShow(shipment.id)"
            >
              <td class="px-6 py-4 relative">
                <div v-if="shipment.has_notes" 
                    class="absolute top-1 left-1 text-blue-500 opacity-70"
                    title="Has notes">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                  </svg>
                </div>
                {{ shipment.status }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ shipment.bol || '—' }}</td>
              <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">{{ shipment.shipment_number }}</td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ shipment.pickup_location?.short_code || '—' }}</td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ shipment.dc_location?.short_code || '—' }}</td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400" :title="getFullDateTime(shipment.drop_date)">
                {{ formatDate(shipment.drop_date) }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400" :title="getFullDateTime(shipment.pickup_date)">
                {{ formatDate(shipment.pickup_date) }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400" :title="getFullDateTime(shipment.delivery_date)">
                {{ formatDate(shipment.delivery_date) }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ shipment.carrier?.name || '—' }}</td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ shipment.trailer || '—' }}</td>
              <td class="px-6 py-4 text-center space-x-4" @click.stop>
                <a
                  :href="route('admin.shipments.edit', shipment.id)"
                  class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300"
                  title="Edit"
                >
                  ✏️
                </a>
                <button
                  v-if="hasAdminAccess"
                  @click.stop="destroy(shipment.id)"
                  class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                  title="Delete"
                >
                  🗑️
                </button>
              </td>
            </tr>

            <tr v-if="!shipments.data.length">
              <td colspan="11" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                No shipments found.<br/>
                <span class="text-sm">Try adjusting filters or search.</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="shipments.data.length" class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between text-sm text-gray-700 dark:text-gray-300">
        <div>
          Showing {{ shipments.from || 0 }}–{{ shipments.to || 0 }} of {{ shipments.total }}
        </div>

        <div class="flex gap-1 mt-2 sm:mt-0">
          <template v-for="(link, i) in shipments.links" :key="i">
            <button
              v-if="link.label !== 'Previous' && link.label !== 'Next'"
              :disabled="!link.url"
              @click="changePage(link.url)"
              class="px-3 py-1 rounded"
              :class="{
                'bg-blue-600 text-white': link.active,
                'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600': !link.active && link.url,
                'opacity-50 cursor-not-allowed': !link.url
              }"
              v-html="link.label"
            />
          </template>
        </div>
      </div>
    </div>

    <!-- Teleported Dropdowns -->
    <Teleport to="body">
      <!-- Status -->
      <div v-if="showStatusFilter" ref="statusDropdownRoot" class="fixed z-50 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-xl" :style="statusDropdownStyle">
        <div class="p-4">
          <select v-model="selectedStatuses" multiple class="w-64 h-48 border dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700">
            <option v-for="s in props.statuses" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div class="border-t dark:border-gray-700 p-3 flex justify-between text-sm">
          <span>{{ selectedStatuses.length }} / {{ props.statuses.length }}</span>
          <button @click="selectedStatuses = [...props.statuses]" class="text-blue-600 hover:underline">Select all</button>
        </div>
      </div>

      <!-- Pickup -->
      <div v-if="showPickupFilter" ref="pickupDropdownRoot" class="fixed z-50 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-xl" :style="pickupDropdownStyle">
        <div class="p-4">
          <select v-model="selectedPickupLocations" multiple class="w-64 h-48 border dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700">
            <option v-for="code in props.all_shipper_codes" :key="code" :value="code">{{ code }}</option>
          </select>
        </div>
        <div class="border-t dark:border-gray-700 p-3 flex justify-between text-sm">
          <span>{{ selectedPickupLocations.length }} / {{ props.all_shipper_codes.length }}</span>
          <button @click="selectedPickupLocations = [...props.all_shipper_codes]" class="text-blue-600 hover:underline">Select all</button>
        </div>
      </div>

      <!-- DC -->
      <div v-if="showDcFilter" ref="dcDropdownRoot" class="fixed z-50 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-xl" :style="dcDropdownStyle">
        <!-- similar structure as above -->
        <div class="p-4">
          <select v-model="selectedDcLocations" multiple class="w-64 h-48 border dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700">
            <option v-for="code in props.all_dc_codes" :key="code" :value="code">{{ code }}</option>
          </select>
        </div>
        <div class="border-t dark:border-gray-700 p-3 flex justify-between text-sm">
          <span>{{ selectedDcLocations.length }} / {{ props.all_dc_codes.length }}</span>
          <button @click="selectedDcLocations = [...props.all_dc_codes]" class="text-blue-600 hover:underline">Select all</button>
        </div>
      </div>

      <!-- Carrier -->
      <div v-if="showCarrierFilter" ref="carrierDropdownRoot" class="fixed z-50 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-xl" :style="carrierDropdownStyle">
        <div class="p-4">
          <select v-model="selectedCarriers" multiple class="w-64 h-48 border dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700">
            <option v-for="name in props.all_carrier_names" :key="name" :value="name">{{ name }}</option>
          </select>
        </div>
        <div class="border-t dark:border-gray-700 p-3 flex justify-between text-sm">
          <span>{{ selectedCarriers.length }} / {{ props.all_carrier_names.length }}</span>
          <button @click="selectedCarriers = [...props.all_carrier_names]" class="text-blue-600 hover:underline">Select all</button>
        </div>
      </div>

      <!-- Drop Date -->
      <div v-if="showDropDateFilter" ref="dropDateDropdownRoot" class="fixed z-50 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-xl" :style="dropDateDropdownStyle">
        <div class="p-4 space-y-4">
          <div>
            <label class="block text-sm mb-1">From</label>
            <input v-model="dropStart" type="date" class="w-full border dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">To</label>
            <input v-model="dropEnd" type="date" :min="dropStart" class="w-full border dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700" />
          </div>
        </div>
        <div class="border-t dark:border-gray-700 p-3 flex justify-between">
          <button @click="clearDropDate" class="text-gray-600 hover:underline">Clear</button>
          <button @click="showDropDateFilter = false" class="text-blue-600 hover:underline">Close</button>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
