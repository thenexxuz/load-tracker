<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import Pagination from '@/components/Pagination.vue'
import { Confirm, Notify } from 'notiflix'
import { onMounted, ref, watch } from 'vue'

const page = usePage()

const props = defineProps<{
  locations: {
    data: Array<{
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
      created_at: string
    }>
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
}>()

const search = ref('')

// Watch search → reload with query params
watch(search, () => {
  router.get(route('admin.locations.index'), {
    search: search.value || null,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
})

const changePage = (url: string) => {
  router.get(url, {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const changePerPage = (value: number) => {
  router.get(route('admin.locations.index'), {
    search: search.value || null,
    per_page: value,
    page: 1,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

// ── Export Locations ──────────────────────────────────────────────────────────
const exportLocations = () => {
  window.location.href = route('admin.locations.export')
}

// ── Import Locations ──────────────────────────────────────────────────────────
const importInput = ref<HTMLInputElement | null>(null)

const triggerImport = () => {
  importInput.value?.click()
}

const handleImport = (event: Event) => {
  const fileInput = event.target as HTMLInputElement
  const file = fileInput.files?.[0]

  if (!file) return

  Confirm.show(
    'Import Locations',
    'This will update existing locations by short_code and add new ones. Existing data will be overwritten where matched. Continue?',
    'Yes, import',
    'Cancel',
    () => {
      const formData = new FormData()
      formData.append('file', file)

      router.post(route('admin.locations.import'), formData, {
        onSuccess: () => {
          Notify.success('Locations imported successfully!')
          router.reload({ only: ['locations', 'flash'] })
          fileInput.value = ''
        },
        onError: (errors) => {
          Notify.failure(errors.file || 'Import failed. Please check the file format.')
        },
      })
    },
    () => {
      fileInput.value = ''
    },
    {
      okButtonBackground: '#10b981',
      titleColor: '#111827',
    }
  )
}

// Delete with confirmation
const destroy = (id: number) => {
  Confirm.show(
    'Delete Location',
    'Are you sure you want to delete this location? This action cannot be undone.',
    'Yes, delete it',
    'Cancel',
    () => {
      router.delete(route('admin.locations.destroy', id), {
        onSuccess: () => {
          Notify.success('Location has been deleted.')
        },
        onError: () => {
          Notify.failure('Failed to delete location.')
        }
      })
    },
    () => {},
    {
      titleColor: '#ff0000',
      okButtonBackground: '#ff0000',
    }
  )
}

onMounted(() => {
  if (page.props.flash?.success) Notify.success(page.props.flash.success)
  if (page.props.flash?.error) Notify.failure(page.props.flash.error)
  if (page.props.flash?.info) Notify.info(page.props.flash.info)
  if (page.props.flash?.warning) Notify.warning(page.props.flash.warning)
})
</script>

<template>
  <Head title="Locations" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Locations
        </h1>

        <div class="flex flex-wrap gap-3">
          <!-- Export Button -->
          <button
            @click="exportLocations"
            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export Locations (CSV)
          </button>

          <!-- Import Button -->
          <label
            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm cursor-pointer transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
            </svg>
            Import Locations (CSV)
            <input
              ref="importInput"
              type="file"
              accept=".csv,.txt"
              class="hidden"
              @change="handleImport"
            />
          </label>

          <!-- Create Button -->
          <Link
            :href="route('admin.locations.create')"
            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors"
          >
            + New Location
          </Link>
        </div>
      </div>

      <!-- Filters -->
      <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
          <input
            v-model="search"
            type="text"
            placeholder="Search by short code, name, city, state..."
            class="w-full p-3 border rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="flex items-center space-x-3">
          <label class="text-sm text-gray-700 dark:text-gray-300">Per page:</label>
          <select
            v-model="perPage"
            class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow border dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Short Code
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Name
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Type
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  City / State
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Coordinates
                </th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="location in locations.data"
                :key="location.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  <Link
                    :href="route('admin.locations.show', location.id)"
                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline"
                  >
                    {{ location.short_code }}
                  </Link>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ location.name || '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">
                  {{ location.type.replace('_', ' ') }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ [location.city, location.state].filter(Boolean).join(', ') || '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  <span v-if="location.latitude && location.longitude">
                    {{ location.latitude.toFixed(6) }}, {{ location.longitude.toFixed(6) }}
                  </span>
                  <span v-else>—</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                  <div class="flex justify-center space-x-4">
                    <Link
                      :href="route('admin.locations.edit', location.id)"
                      class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                    >
                      <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                      </svg>
                    </Link>
                    <button
                      @click="destroy(location.id)"
                      class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                    >
                      <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="!locations.data.length">
                <td colspan="6" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                  No locations found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <Pagination
          :pagination="locations"
          @pageChange="changePage"
          @perPageChange="changePerPage"
        />
      </div>
    </div>
  </AdminLayout>
</template>
