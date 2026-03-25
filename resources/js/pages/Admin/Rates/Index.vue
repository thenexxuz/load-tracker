<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import AdminLayout from '@/layouts/AppLayout.vue'
import Pagination from '@/components/Pagination.vue'
import { Confirm, Notify } from 'notiflix'
import { format } from 'date-fns' // optional: better date formatting (npm install date-fns)
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps<{
  rates: {
    data: Array<{
      id: number
      name: string | null
      type: 'flat' | 'per_mile'
      rate: number
      pickup_location?: { short_code: string; name?: string | null }
      destination_city: string | null
      destination_state: string | null
      destination_country: string | null
      carrier?: { name: string; short_code?: string }
      effective_from: string | null
      effective_to: string | null
      created_at: string
    }>
    current_page: number
    last_page: number
    from: number
    to: number
    total: number
    per_page: number
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  carriers: Array<{
    id: number
    name: string
    short_code?: string | null
  }>
  filters?: {
    search?: string | null
    type?: 'flat' | 'per_mile' | null
    carrier_id?: number | string | null
    status?: 'active' | 'inactive' | null
    sort_by?: 'name' | null
    sort_direction?: 'asc' | 'desc' | null
  }
}>()

const typeFilter = ref(props.filters?.type ?? '')
const carrierFilter = ref(props.filters?.carrier_id ? String(props.filters.carrier_id) : '')
const statusFilter = ref(props.filters?.status ?? '')

const currentParams = computed(() => ({
  search: props.filters?.search || undefined,
  type: typeFilter.value || undefined,
  carrier_id: carrierFilter.value || undefined,
  status: statusFilter.value || undefined,
  sort_by: props.filters?.sort_by || undefined,
  sort_direction: props.filters?.sort_direction || undefined,
  per_page: props.rates.per_page,
}))

const applyFilters = () => {
  router.get(
    route('admin.rates.index'),
    {
      ...currentParams.value,
      page: 1,
    },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

watch([typeFilter, carrierFilter, statusFilter], () => {
  applyFilters()
})

const typeHeaderText = computed(() => {
  if (typeFilter.value === 'flat') {
    return 'Type: Flat'
  }

  if (typeFilter.value === 'per_mile') {
    return 'Type: Per Mile'
  }

  return 'Type'
})

const selectedCarrier = computed(() => {
  if (!carrierFilter.value) {
    return null
  }

  return props.carriers.find((carrier) => String(carrier.id) === carrierFilter.value) ?? null
})

const carrierHeaderText = computed(() => {
  if (!selectedCarrier.value) {
    return 'Carrier'
  }

  return selectedCarrier.value.short_code ?? selectedCarrier.value.name
})

const statusHeaderText = computed(() => {
  if (statusFilter.value === 'active') {
    return 'Status: Active'
  }

  if (statusFilter.value === 'inactive') {
    return 'Status: Inactive'
  }

  return 'Status'
})

const showTypeFilter = ref(false)
const showCarrierFilter = ref(false)
const showStatusFilter = ref(false)

const typeFilterRef = ref<HTMLElement | null>(null)
const carrierFilterRef = ref<HTMLElement | null>(null)
const statusFilterRef = ref<HTMLElement | null>(null)

const typeDropdownRoot = ref<HTMLElement | null>(null)
const carrierDropdownRoot = ref<HTMLElement | null>(null)
const statusDropdownRoot = ref<HTMLElement | null>(null)

const typeDropdownStyle = ref({ top: '0px', left: '0px' })
const carrierDropdownStyle = ref({ top: '0px', left: '0px' })
const statusDropdownStyle = ref({ top: '0px', left: '0px' })

const updatePosition = (
  headerRef: typeof typeFilterRef,
  styleRef: typeof typeDropdownStyle
) => {
  if (!headerRef.value) {
    return
  }

  const rect = headerRef.value.getBoundingClientRect()

  styleRef.value = {
    top: `${rect.bottom + window.scrollY + 8}px`,
    left: `${rect.left + window.scrollX}px`,
  }
}

watch(showTypeFilter, (value) => value && nextTick(() => updatePosition(typeFilterRef, typeDropdownStyle)))
watch(showCarrierFilter, (value) => value && nextTick(() => updatePosition(carrierFilterRef, carrierDropdownStyle)))
watch(showStatusFilter, (value) => value && nextTick(() => updatePosition(statusFilterRef, statusDropdownStyle)))

onClickOutside(typeFilterRef, () => {
  showTypeFilter.value = false
}, { ignore: [typeDropdownRoot] })

onClickOutside(carrierFilterRef, () => {
  showCarrierFilter.value = false
}, { ignore: [carrierDropdownRoot] })

onClickOutside(statusFilterRef, () => {
  showStatusFilter.value = false
}, { ignore: [statusDropdownRoot] })

const closeAllHeaderFilters = () => {
  showTypeFilter.value = false
  showCarrierFilter.value = false
  showStatusFilter.value = false
}

const toggleTypeFilter = () => {
  showTypeFilter.value = !showTypeFilter.value

  if (showTypeFilter.value) {
    showCarrierFilter.value = false
    showStatusFilter.value = false
  }
}

const toggleCarrierFilter = () => {
  showCarrierFilter.value = !showCarrierFilter.value

  if (showCarrierFilter.value) {
    showTypeFilter.value = false
    showStatusFilter.value = false
  }
}

const toggleStatusFilter = () => {
  showStatusFilter.value = !showStatusFilter.value

  if (showStatusFilter.value) {
    showTypeFilter.value = false
    showCarrierFilter.value = false
  }
}

const deleteRate = (id: number) => {
  Confirm.prompt(
    'Delete Rate',
    'Are you sure you want to delete this rate?',
    'This cannot be undone.',
    'Yes, delete',
    'Cancel',
    () => {
      router.delete(route('admin.rates.destroy', {
        rate: id,
        ...currentParams.value,
        page: props.rates.current_page,
      }), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          Notify.success('Rate deleted successfully.')
        },
        onError: () => {
          Notify.failure('Failed to delete rate.')
        },
      })
    },
    () => {},
    {
      titleColor: '#ef4444',
      okButtonBackground: '#ef4444',
    }
  )
}

const formatDate = (date: string | null): string => {
  if (!date) return '—'
  try {
    return format(new Date(date), 'MMM d, yyyy')
  } catch {
    return 'Invalid date'
  }
}

const isActive = (from: string | null, to: string | null): boolean => {
  const now = new Date()
  const start = from ? new Date(from) : null
  const end = to ? new Date(to) : null

  if (start && start > now) return false
  if (end && end < now) return false
  return true
}

const toggleSort = (column: 'name') => {
  const isCurrentColumn = props.filters?.sort_by === column
  const nextDirection = isCurrentColumn && props.filters?.sort_direction === 'asc' ? 'desc' : 'asc'

  router.get(
    route('admin.rates.index'),
    {
      ...currentParams.value,
      sort_by: column,
      sort_direction: nextDirection,
      page: 1,
    },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

const clearTypeFilter = () => {
  typeFilter.value = ''
  showTypeFilter.value = false
}

const clearCarrierFilter = () => {
  carrierFilter.value = ''
  showCarrierFilter.value = false
}

const clearStatusFilter = () => {
  statusFilter.value = ''
  showStatusFilter.value = false
}

const sortIndicator = (column: 'name'): string => {
  if (props.filters?.sort_by !== column) {
    return '▾'
  }

  return props.filters.sort_direction === 'desc' ? '▾' : '▴'
}

// Pagination functions
const changePage = (url: string) => {
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  })
}

const changePerPage = (value: number) => {
  router.get(
    route('admin.rates.index'),
    { ...currentParams.value, per_page: value, page: 1 },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}
</script>

<template>
  <Head title="Rates" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Freight Rates
        </h1>
        <Link
          :href="route('admin.rates.create')"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
        >
          Create New Rate
        </Link>
      </div>

      <!-- Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 text-left uppercase tracking-wider transition-colors hover:text-gray-700 dark:hover:text-gray-200"
                    @click="toggleSort('name')"
                  >
                    <span>Name</span>
                    <span class="text-[10px] font-semibold leading-none" aria-hidden="true">
                      {{ sortIndicator('name') }}
                    </span>
                  </button>
                </th>
                <th
                  ref="typeFilterRef"
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer"
                  @click="toggleTypeFilter"
                >
                  <div class="flex items-center justify-between select-none gap-2">
                    <span>{{ typeHeaderText }}</span>
                    <span class="text-[10px]">▼</span>
                  </div>
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Rate
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Lane (Pickup → Destination)
                </th>
                <th
                  ref="carrierFilterRef"
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer"
                  @click="toggleCarrierFilter"
                >
                  <div class="flex items-center justify-between select-none gap-2">
                    <span>{{ carrierHeaderText }}</span>
                    <span class="text-[10px]">▼</span>
                  </div>
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Valid Period
                </th>
                <th
                  ref="statusFilterRef"
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer"
                  @click="toggleStatusFilter"
                >
                  <div class="flex items-center justify-between select-none gap-2">
                    <span>{{ statusHeaderText }}</span>
                    <span class="text-[10px]">▼</span>
                  </div>
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="rate in rates.data" :key="rate.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ rate.name || 'Unnamed Rate' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 capitalize">
                  {{ rate.type }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  <span v-if="rate.type === 'flat'">
                    ${{ rate.rate.toFixed(2) }} flat
                  </span>
                  <span v-else>
                    ${{ rate.rate.toFixed(2) }}/mi
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                  {{ rate.pickup_location?.short_code || '—' }}
                  →
                  <span v-if="rate.destination_city || rate.destination_state || rate.destination_country">
                    {{ [rate.destination_city, rate.destination_state, rate.destination_country].filter(Boolean).join(', ') }}
                  </span>
                  <span v-else>—</span>
                  <div v-if="rate.pickup_location?.name" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ rate.pickup_location.name }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  {{ rate.carrier?.name || '—' }}
                  <span v-if="rate.carrier?.short_code" class="text-xs text-gray-500 dark:text-gray-400">
                    ({{ rate.carrier.short_code }})
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                  <div>
                    From: {{ formatDate(rate.effective_from) }}
                  </div>
                  <div>
                    To: {{ formatDate(rate.effective_to) || 'No end date' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    v-if="isActive(rate.effective_from, rate.effective_to)"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                  >
                    Active
                  </span>
                  <span
                    v-else
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
                  >
                    Inactive
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <Link
                    :href="route('admin.rates.edit', rate.id)"
                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3"
                  >
                    Edit
                  </Link>
                  <button
                    @click="deleteRate(rate.id)"
                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                  >
                    Delete
                  </button>
                </td>
              </tr>

              <tr v-if="rates.data.length === 0">
                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                  No rates found. Create one to get started.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <Pagination
          :pagination="rates"
          @pageChange="changePage"
          @perPageChange="changePerPage"
        />
      </div>

      <Teleport to="body">
        <div
          v-if="showTypeFilter"
          ref="typeDropdownRoot"
          class="fixed z-[9999] min-w-[220px] rounded-md border border-gray-300 bg-white shadow-2xl dark:border-gray-600 dark:bg-gray-800"
          :style="typeDropdownStyle"
        >
          <div class="p-4">
            <select
              v-model="typeFilter"
              class="w-full rounded border border-gray-300 bg-white p-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            >
              <option value="">All types</option>
              <option value="flat">Flat</option>
              <option value="per_mile">Per mile</option>
            </select>
          </div>
          <div class="flex items-center justify-between border-t border-gray-200 p-3 dark:border-gray-700">
            <button
              type="button"
              class="rounded-md bg-gray-200 px-4 py-2 text-sm transition-colors hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
              @click="clearTypeFilter"
            >
              Clear
            </button>
            <button
              type="button"
              class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white transition-colors hover:bg-blue-700"
              @click="closeAllHeaderFilters"
            >
              Close
            </button>
          </div>
        </div>

        <div
          v-if="showCarrierFilter"
          ref="carrierDropdownRoot"
          class="fixed z-[9999] min-w-[280px] rounded-md border border-gray-300 bg-white shadow-2xl dark:border-gray-600 dark:bg-gray-800"
          :style="carrierDropdownStyle"
        >
          <div class="p-4">
            <select
              v-model="carrierFilter"
              class="w-full rounded border border-gray-300 bg-white p-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            >
              <option value="">All carriers</option>
              <option
                v-for="carrier in carriers"
                :key="carrier.id"
                :value="String(carrier.id)"
              >
                {{ carrier.short_code ? `${carrier.name} (${carrier.short_code})` : carrier.name }}
              </option>
            </select>
          </div>
          <div class="flex items-center justify-between border-t border-gray-200 p-3 dark:border-gray-700">
            <button
              type="button"
              class="rounded-md bg-gray-200 px-4 py-2 text-sm transition-colors hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
              @click="clearCarrierFilter"
            >
              Clear
            </button>
            <button
              type="button"
              class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white transition-colors hover:bg-blue-700"
              @click="closeAllHeaderFilters"
            >
              Close
            </button>
          </div>
        </div>

        <div
          v-if="showStatusFilter"
          ref="statusDropdownRoot"
          class="fixed z-[9999] min-w-[220px] rounded-md border border-gray-300 bg-white shadow-2xl dark:border-gray-600 dark:bg-gray-800"
          :style="statusDropdownStyle"
        >
          <div class="p-4">
            <select
              v-model="statusFilter"
              class="w-full rounded border border-gray-300 bg-white p-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            >
              <option value="">All statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="flex items-center justify-between border-t border-gray-200 p-3 dark:border-gray-700">
            <button
              type="button"
              class="rounded-md bg-gray-200 px-4 py-2 text-sm transition-colors hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
              @click="clearStatusFilter"
            >
              Clear
            </button>
            <button
              type="button"
              class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white transition-colors hover:bg-blue-700"
              @click="closeAllHeaderFilters"
            >
              Close
            </button>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>