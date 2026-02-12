<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import { ref, watch, onMounted, computed, nextTick } from 'vue'

import AdminLayout from '@/layouts/AppLayout.vue'


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
}>()

const page = usePage()

// ── Status filter ───────────────────────────────────────────────────────
const selectedStatuses = ref<string[]>([...props.statuses])
const excludedStatuses = computed(() =>
  props.statuses.filter(s => !selectedStatuses.value.includes(s))
)

// ── Pickup Location filter ──────────────────────────────────────────────
const selectedPickupLocations = ref<string[]>([...props.all_shipper_codes])
const excludedPickupLocations = computed(() =>
  props.all_shipper_codes.filter(s => !selectedPickupLocations.value.includes(s))
)

// ── DC Location filter ──────────────────────────────────────────────────
const selectedDcLocations = ref<string[]>([...props.all_dc_codes])
const excludedDcLocations = computed(() =>
  props.all_dc_codes.filter(s => !selectedDcLocations.value.includes(s))
)

// ── Carrier filter ──────────────────────────────────────────────────────
const selectedCarriers = ref<string[]>([...props.all_carrier_names])
const excludedCarriers = computed(() =>
  props.all_carrier_names.filter(c => !selectedCarriers.value.includes(c))
)

// ── Drop Date range filter ──────────────────────────────────────────────
const dropStart = ref<string>('')
const dropEnd   = ref<string>('')

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

// ── Shared filter application ───────────────────────────────────────────
function applyFilters() {
  router.post(route('admin.shipments.filter'), {
    excluded_statuses: excludedStatuses.value.length ? excludedStatuses.value : undefined,
    excluded_pickup_locations: excludedPickupLocations.value.length ? excludedPickupLocations.value : undefined,
    excluded_dc_locations: excludedDcLocations.value.length ? excludedDcLocations.value : undefined,
    excluded_carriers: excludedCarriers.value.length ? excludedCarriers.value : undefined,
    drop_start: dropStart.value || undefined,
    drop_end: dropEnd.value || undefined,
    search: search.value.trim() || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
}

watch(
  [selectedStatuses, selectedPickupLocations, selectedDcLocations, selectedCarriers, dropStart, dropEnd],
  applyFilters,
  { deep: true }
)

// Search
const search = ref('')
watch(search, applyFilters)

// ── Dropdown visibility ─────────────────────────────────────────────────
const showStatusFilter   = ref(false)
const showPickupFilter   = ref(false)
const showDcFilter       = ref(false)
const showCarrierFilter  = ref(false)
const showDropDateFilter = ref(false)

// Header refs (for positioning & outside-click detection)
const statusFilterRef    = ref<HTMLElement | null>(null)
const pickupFilterRef    = ref<HTMLElement | null>(null)
const dcFilterRef        = ref<HTMLElement | null>(null)
const carrierFilterRef   = ref<HTMLElement | null>(null)
const dropDateFilterRef  = ref<HTMLElement | null>(null)

// Dropdown root refs (to ignore clicks inside them)
const statusDropdownRoot   = ref<HTMLElement | null>(null)
const pickupDropdownRoot   = ref<HTMLElement | null>(null)
const dcDropdownRoot       = ref<HTMLElement | null>(null)
const carrierDropdownRoot  = ref<HTMLElement | null>(null)
const dropDateDropdownRoot = ref<HTMLElement | null>(null)

// Position styles for fixed dropdowns
const statusDropdownStyle   = ref({ top: '0px', left: '0px' })
const pickupDropdownStyle   = ref({ top: '0px', left: '0px' })
const dcDropdownStyle       = ref({ top: '0px', left: '0px' })
const carrierDropdownStyle  = ref({ top: '0px', left: '0px' })
const dropDateDropdownStyle = ref({ top: '0px', left: '0px' })

// Update position when dropdown opens
const updatePosition = (headerRef: any, styleRef: any) => {
  if (!headerRef.value) return
  const rect = headerRef.value.getBoundingClientRect()
  styleRef.value = {
    top: `${rect.bottom + window.scrollY + 8}px`, // +8px gap
    left: `${rect.left + window.scrollX}px`,
  }
}

watch(showStatusFilter,   val => val && nextTick(() => updatePosition(statusFilterRef,   statusDropdownStyle)))
watch(showPickupFilter,   val => val && nextTick(() => updatePosition(pickupFilterRef,   pickupDropdownStyle)))
watch(showDcFilter,       val => val && nextTick(() => updatePosition(dcFilterRef,       dcDropdownStyle)))
watch(showCarrierFilter,  val => val && nextTick(() => updatePosition(carrierFilterRef,  carrierDropdownStyle)))
watch(showDropDateFilter, val => val && nextTick(() => updatePosition(dropDateFilterRef, dropDateDropdownStyle)))

// Outside click – ignore clicks inside the dropdown content
onClickOutside(statusFilterRef,   () => showStatusFilter.value = false,   { ignore: [statusDropdownRoot] })
onClickOutside(pickupFilterRef,   () => showPickupFilter.value = false,   { ignore: [pickupDropdownRoot] })
onClickOutside(dcFilterRef,       () => showDcFilter.value = false,       { ignore: [dcDropdownRoot] })
onClickOutside(carrierFilterRef,  () => showCarrierFilter.value = false,  { ignore: [carrierDropdownRoot] })
onClickOutside(dropDateFilterRef, () => showDropDateFilter.value = false, { ignore: [dropDateDropdownRoot] })

// Toggle functions
const toggleStatusFilter = () => {
  showStatusFilter.value = !showStatusFilter.value
  if (showStatusFilter.value) {
    showPickupFilter.value = false
    showDcFilter.value = false
    showCarrierFilter.value = false
    showDropDateFilter.value = false
  }
}

const togglePickupFilter = () => {
  showPickupFilter.value = !showPickupFilter.value
  if (showPickupFilter.value) {
    showStatusFilter.value = false
    showDcFilter.value = false
    showCarrierFilter.value = false
    showDropDateFilter.value = false
  }
}

const toggleDcFilter = () => {
  showDcFilter.value = !showDcFilter.value
  if (showDcFilter.value) {
    showStatusFilter.value = false
    showPickupFilter.value = false
    showCarrierFilter.value = false
    showDropDateFilter.value = false
  }
}

const toggleCarrierFilter = () => {
  showCarrierFilter.value = !showCarrierFilter.value
  if (showCarrierFilter.value) {
    showStatusFilter.value = false
    showPickupFilter.value = false
    showDcFilter.value = false
    showDropDateFilter.value = false
  }
}

const toggleDropDateFilter = () => {
  showDropDateFilter.value = !showDropDateFilter.value
  if (showDropDateFilter.value) {
    showStatusFilter.value = false
    showPickupFilter.value = false
    showDcFilter.value = false
    showCarrierFilter.value = false
  }
}

// ── Dynamic header text ─────────────────────────────────────────────────
const statusHeaderText = computed(() => {
  const total = props.statuses.length
  const shown = selectedStatuses.value.length
  if (shown === total) return 'Status'
  if (shown === 0) return 'Status (none)'
  return `Status (${shown}/${total})`
})

const pickupHeaderText = computed(() => {
  const total = props.all_shipper_codes.length
  const shown = selectedPickupLocations.value.length
  if (shown === total) return 'Pickup Location'
  if (shown === 0) return 'Pickup Location (none)'
  return `Pickup Location (${shown}/${total})`
})

const dcHeaderText = computed(() => {
  const total = props.all_dc_codes.length
  const shown = selectedDcLocations.value.length
  if (shown === total) return 'DC'
  if (shown === 0) return 'DC (none)'
  return `DC (${shown}/${total})`
})

const carrierHeaderText = computed(() => {
  const total = props.all_carrier_names.length
  const shown = selectedCarriers.value.length
  if (shown === total) return 'Carrier'
  if (shown === 0) return 'Carrier (none)'
  return `Carrier (${shown}/${total})`
})

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
    Swal.fire({
      icon: 'warning',
      title: 'No file selected',
      text: 'Please choose an XLSX file first.',
    })
    return
  }

  pbiImportForm.post(route('admin.shipments.pbi-import'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      showPbiImportModal.value = false
      selectedPbiFile.value = null
      pbiImportForm.reset()
      Swal.fire({
        icon: 'success',
        title: 'Imported!',
        text: 'Shipments imported from PBI successfully.',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
      })
      router.reload({ only: ['shipments'] })
    },
    onError: (errors) => {
      let errorMessage = 'Import failed. Please check the file format.'
      if (typeof errors === 'object' && errors !== null) {
        errorMessage = Object.values(errors).join('<br>')
      }
      Swal.fire({
        icon: 'error',
        title: 'PBI Import Failed',
        html: errorMessage
      })
    }
  })
}

// ── Delete ──────────────────────────────────────────────────────────────
const destroy = async (id: number) => {
  const result = await Swal.fire({
    title: 'Delete Shipment?',
    text: "This action cannot be undone.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  })

  if (result.isConfirmed) {
    router.delete(route('admin.shipments.destroy', id), {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Deleted!',
          text: 'Shipment has been deleted.',
          timer: 2000,
          showConfirmButton: false,
          toast: true,
          position: 'top-end'
        })
      },
      onError: () => {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to delete shipment.'
        })
      },
      preserveScroll: true,
    })
  }
}

// ── Flash messages ──────────────────────────────────────────────────────
onMounted(() => {
  if (page.props.flash?.success) {
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: page.props.flash.success,
      timer: 3000,
      toast: true,
      position: 'top-end'
    })
  }

  if (page.props.flash?.warning) {
    Swal.fire({
      icon: 'warning',
      title: 'Partial Import Warning',
      html: `${page.props.flash.warning}<br><br>
             <a href="${route('admin.shipments.download-failed-tsv')}"
                class="underline font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800">
               Download Failed Rows TSV
             </a>`,
      timer: 8000,
      showConfirmButton: false,
      toast: true,
      position: 'top-end',
      allowOutsideClick: true
    })
  }
})

// ── Date helpers ────────────────────────────────────────────────────────
const formatDate = (dateString: string | null) => {
  if (!dateString) return '—'
  return dateString.split('T')[0] || '—'
}

const getFullDateTime = (dateString: string | null) => {
  if (!dateString) return 'No date/time recorded'
  return dateString
}

// ── Row click → Show ────────────────────────────────────────────────────
const goToShow = (id: number) => {
  router.visit(route('admin.shipments.show', id))
}

// Change page
const changePage = (url: string | null) => {
  if (url) {
    router.visit(url, {
      preserveState: true,
      preserveScroll: true,
    })
  }
}

// Change per page
const changePerPage = (e: Event) => {
  const value = (e.target as HTMLSelectElement).value
  router.get(
    route('admin.shipments.index'),
    { search: search.value || null, per_page: value, page: 1 },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

const { auth } = usePage().props
const userRoles = auth?.user?.roles || []
const hasAdminAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
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
              <li>Ship Date → pickup_date (time from DC location)</li>
              <li>Deliver Date → delivery_date (time from DC location)</li>
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
                  class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 dark:file:bg-gray-600 dark:file:text-gray-200"
                  required
                />
              </div>

              <div class="flex justify-end space-x-3">
                <button
                  type="button"
                  @click="showPbiImportModal = false"
                  class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="!selectedPbiFile || pbiImportForm.processing"
                  class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
            placeholder="Search by shipment number, BOL or PO..."
            class="w-full border border-gray-300 dark:border-gray-600 rounded-md p-2.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
          />
        </div>

        <div class="flex items-center space-x-3">
          <label class="text-sm text-gray-700 dark:text-gray-300">Items per page:</label>
          <select
            @change="changePerPage"
            :value="shipments.per_page"
            class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="w-full">
        <div class="overflow-x-auto rounded-t-lg">
          <table class="w-full min-w-max border-collapse bg-white dark:bg-gray-800 shadow-md dark:shadow-gray-900/30">
            <thead>
              <tr class="bg-gray-100 dark:bg-gray-700 text-left">
                <!-- Status -->
                <th
                  class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[160px] pointer-events-auto"
                  ref="statusFilterRef"
                  @click="toggleStatusFilter"
                >
                  <div class="flex items-center justify-between select-none pointer-events-none">
                    {{ statusHeaderText }}
                    <span class="ml-1 text-xs">▼</span>
                  </div>
                </th>

                <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">BOL</th>
                <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Shipment Number</th>

                <!-- Pickup Location -->
                <th
                  class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[180px] pointer-events-auto"
                  ref="pickupFilterRef"
                  @click="togglePickupFilter"
                >
                  <div class="flex items-center justify-between select-none pointer-events-none">
                    {{ pickupHeaderText }}
                    <span class="ml-1 text-xs">▼</span>
                  </div>
                </th>

                <!-- DC -->
                <th
                  class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[140px] pointer-events-auto"
                  ref="dcFilterRef"
                  @click="toggleDcFilter"
                >
                  <div class="flex items-center justify-between select-none pointer-events-none">
                    {{ dcHeaderText }}
                    <span class="ml-1 text-xs">▼</span>
                  </div>
                </th>

                <!-- Drop Date -->
                <th
                  class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[220px] pointer-events-auto"
                  ref="dropDateFilterRef"
                  @click="toggleDropDateFilter"
                >
                  <div class="flex items-center justify-between select-none pointer-events-none">
                    {{ dropDateHeaderText }}
                    <span class="ml-1 text-xs">▼</span>
                  </div>
                </th>

                <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Pickup Date</th>
                <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Delivery Date</th>

                <!-- Carrier -->
                <th
                  class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 cursor-pointer min-w-[160px] pointer-events-auto"
                  ref="carrierFilterRef"
                  @click="toggleCarrierFilter"
                >
                  <div class="flex items-center justify-between select-none pointer-events-none">
                    {{ carrierHeaderText }}
                    <span class="ml-1 text-xs">▼</span>
                  </div>
                </th>

                <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Trailer</th>
                <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="shipment in shipments.data"
                :key="shipment.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                @click="goToShow(shipment.id)"
              >
                <td class="px-6 py-4 capitalize text-gray-600 dark:text-gray-400">
                  {{ shipment.status }}
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                  {{ shipment.bol || '—' }}
                </td>
                <td class="px-6 py-4 text-gray-900 dark:text-gray-100 font-medium">
                  {{ shipment.shipment_number }}
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                  {{ shipment.pickup_location?.short_code || '—' }}
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                  {{ shipment.dc_location?.short_code || '—' }}
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 group relative cursor-help">
                  <span :title="getFullDateTime(shipment.drop_date)">
                    {{ formatDate(shipment.drop_date) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 group relative cursor-help">
                  <span :title="getFullDateTime(shipment.pickup_date)">
                    {{ formatDate(shipment.pickup_date) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 group relative cursor-help">
                  <span :title="getFullDateTime(shipment.delivery_date)">
                    {{ formatDate(shipment.delivery_date) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                  {{ shipment.carrier?.name || '—' }}
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                  {{ shipment.trailer || '—' }}
                </td>
                <td class="px-6 py-4 text-center space-x-5" @click.stop>
                  <a
                    :href="route('admin.shipments.edit', shipment.id)"
                    class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                    title="Edit Shipment"
                    @click.stop
                  >
                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </a>

                  <button
                    v-if="hasAdminAccess"
                    @click.stop="destroy(shipment.id)"
                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                    title="Delete Shipment"
                  >
                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                  </button>
                </td>
              </tr>

              <!-- Empty state -->
              <tr v-if="!shipments.data?.length">
                <td colspan="11" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400 text-lg font-medium">
                  No shipments found
                  <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
                    Try adjusting search or filters.
                  </p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination – restyled to match app-wide style -->
      <div v-if="shipments.data?.length" class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-b-lg">
        <!-- Showing info -->
        <div class="text-sm text-gray-700 dark:text-gray-300 mb-4 sm:mb-0">
          Showing {{ shipments.from ?? 0 }}–{{ shipments.to ?? 0 }} of {{ shipments.total }} entries
        </div>

        <!-- Pagination buttons -->
        <div class="flex flex-wrap items-center gap-1 sm:gap-2 rounded-b-lg">
          <!-- Page numbers -->
          <template v-for="(link, index) in shipments.links" :key="index">
            <button
              v-if="link.label !== 'Previous' && link.label !== 'Next'"
              :disabled="!link.url"
              @click="changePage(link.url)"
              class="px-3 py-2 rounded-md text-sm font-medium transition-colors"
              :class="{
                'bg-blue-600 text-white': link.active,
                'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700': !link.active && link.url,
                'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed': !link.url
              }"
              v-html="link.label"
            ></button>
          </template>
        </div>
      </div>
    </div>

    <!-- Teleported dropdowns -->
    <Teleport to="body">
      <!-- Status Dropdown -->
      <div
        v-if="showStatusFilter"
        ref="statusDropdownRoot"
        class="fixed z-[9999] bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-2xl"
        :style="statusDropdownStyle"
      >
        <div class="p-4">
          <select v-model="selectedStatuses" multiple class="w-full h-48 border border-gray-300 dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="status in props.statuses" :key="status" :value="status">
              {{ status }}
            </option>
          </select>
        </div>
        <div class="border-t border-gray-200 dark:border-gray-700 p-3 flex justify-between items-center">
          <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ selectedStatuses.length }} / {{ props.statuses.length }}
          </span>
          <button @click="selectedStatuses = [...props.statuses]" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-md text-sm transition-colors">
            Select all
          </button>
        </div>
      </div>

      <!-- Pickup Location Dropdown -->
      <div
        v-if="showPickupFilter"
        ref="pickupDropdownRoot"
        class="fixed z-[9999] bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-2xl"
        :style="pickupDropdownStyle"
      >
        <div class="p-4">
          <select v-model="selectedPickupLocations" multiple class="w-full h-48 border border-gray-300 dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="code in props.all_shipper_codes" :key="code" :value="code">
              {{ code }}
            </option>
          </select>
        </div>
        <div class="border-t border-gray-200 dark:border-gray-700 p-3 flex justify-between items-center">
          <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ selectedPickupLocations.length }} / {{ props.all_shipper_codes.length }}
          </span>
          <button @click="selectedPickupLocations = [...props.all_shipper_codes]" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-md text-sm transition-colors">
            Select all
          </button>
        </div>
      </div>

      <!-- DC Dropdown -->
      <div
        v-if="showDcFilter"
        ref="dcDropdownRoot"
        class="fixed z-[9999] bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-2xl"
        :style="dcDropdownStyle"
      >
        <div class="p-4">
          <select v-model="selectedDcLocations" multiple class="w-full h-48 border border-gray-300 dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="code in props.all_dc_codes" :key="code" :value="code">
              {{ code }}
            </option>
          </select>
        </div>
        <div class="border-t border-gray-200 dark:border-gray-700 p-3 flex justify-between items-center">
          <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ selectedDcLocations.length }} / {{ props.all_dc_codes.length }}
          </span>
          <button @click="selectedDcLocations = [...props.all_dc_codes]" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-md text-sm transition-colors">
            Select all
          </button>
        </div>
      </div>

      <!-- Carrier Dropdown -->
      <div
        v-if="showCarrierFilter"
        ref="carrierDropdownRoot"
        class="fixed z-[9999] bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-2xl"
        :style="carrierDropdownStyle"
      >
        <div class="p-4">
          <select v-model="selectedCarriers" multiple class="w-full h-48 border border-gray-300 dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="name in props.all_carrier_names" :key="name" :value="name">
              {{ name }}
            </option>
          </select>
        </div>
        <div class="border-t border-gray-200 dark:border-gray-700 p-3 flex justify-between items-center">
          <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ selectedCarriers.length }} / {{ props.all_carrier_names.length }}
          </span>
          <button @click="selectedCarriers = [...props.all_carrier_names]" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-md text-sm transition-colors">
            Select all
          </button>
        </div>
      </div>

      <!-- Drop Date Dropdown -->
      <div
        v-if="showDropDateFilter"
        ref="dropDateDropdownRoot"
        class="fixed z-[9999] bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-2xl"
        :style="dropDateDropdownStyle"
      >
        <div class="p-4 space-y-4">
          <div>
            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">From</label>
            <input
              v-model="dropStart"
              type="date"
              class="w-full border border-gray-300 dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">To</label>
            <input
              v-model="dropEnd"
              type="date"
              :min="dropStart"
              class="w-full border border-gray-300 dark:border-gray-600 rounded p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
        <div class="border-t border-gray-200 dark:border-gray-700 p-3 flex justify-between items-center">
          <button @click="clearDropDate" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-md text-sm transition-colors">
            Clear
          </button>
          <button @click="showDropDateFilter = false" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm transition-colors">
            Close
          </button>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
