<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'
import { route } from 'ziggy-js'


import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  carriers: {
    data: Array<{
      id: number
      short_code: string
      name: string
      contact_name: string | null
      email: string | null
      phone: string | null
      is_active: boolean
    }>
    current_page: number
    last_page: number
    from: number
    to: number
    total: number
    per_page: number
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: Record<string, any>
}>()

const page = usePage()

const search = ref(props.filters.search || '')

// Live search
watch(search, (value) => {
  router.get(
    route('admin.carriers.index'),
    { search: value },
    { preserveState: true, replace: true }
  )
})

// Import modal
const showImportModal = ref(false)
const selectedFile = ref<File | null>(null)

const importForm = useForm({
  file: null as File | null,
})

const handleFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  if (input.files?.length) {
    selectedFile.value = input.files[0]
    importForm.file = input.files[0]
  }
}

const importFile = () => {
  if (!importForm.file) {
    Swal.fire({
      icon: 'warning',
      title: 'No file selected',
      text: 'Please choose a TSV file first.',
    })
    return
  }

  importForm.post(route('admin.carriers.import'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      showImportModal.value = false
      selectedFile.value = null
      importForm.reset()
      Swal.fire({
        icon: 'success',
        title: 'Imported!',
        text: 'Carriers imported successfully.',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
      })
      router.reload({ only: ['carriers'] })
    },
    onError: (errors) => {
      let errorMessage = 'Import failed. Please check the file format.'
      if (typeof errors === 'object' && errors !== null) {
        errorMessage = Object.values(errors).join('<br>')
      }
      Swal.fire({
        icon: 'error',
        title: 'Import Failed',
        html: errorMessage
      })
    }
  })
}

// Export
const isExporting = ref(false)

const exportCarriers = () => {
  isExporting.value = true
  const url = route('admin.carriers.export', { search: search.value })
  window.location.href = url

  setTimeout(() => { isExporting.value = false }, 2000)
}

// Delete
const destroy = async (id: number) => {
  const result = await Swal.fire({
    title: 'Delete Carrier?',
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
    router.delete(route('admin.carriers.destroy', id), {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Deleted!',
          text: 'Carrier has been deleted.',
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
          text: 'Failed to delete carrier.'
        })
      },
      preserveScroll: true,
    })
  }
}

// Flash success
onMounted(() => {
  if (page.props.flash?.success) {
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: page.props.flash.success,
      timer: 3000,
      showConfirmButton: false,
      toast: true,
      position: 'top-end'
    })
  }
})

// Helper to count emails and format tooltip
const getEmailInfo = (emails: string | null) => {
  if (!emails || emails.trim() === '') {
    return {
      count: 0,
      tooltip: 'No emails set for this carrier'
    }
  }

  const emailArr = emails.split(/,|;/gi)
    .map(e => e.trim())
    .filter(e => e.length > 0)

  const count = emailArr.length
  const tooltip = count === 0
    ? 'No emails set for this carrier'
    : emailArr.join('; ')

  return { count, tooltip }
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
    route('admin.carriers.index'),
    { search: search.value || null, per_page: value, page: 1 },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

const { auth } = usePage().props
const userRoles = auth?.user?.roles || []
const hasAdminAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
</script>

<template>
  <Head title="Manage Carriers" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Carriers Management
        </h1>
        <div class="space-x-4">
          <a
            v-if="hasAdminAccess"
            :href="route('admin.carriers.create')"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
          >
            Add New Carrier
          </a>

          <button
            v-if="hasAdminAccess"
            @click="showImportModal = true"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
          >
            Import from TSV
          </button>

          <button
            v-if="hasAdminAccess"
            @click="exportCarriers"
            :disabled="isExporting"
            class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors disabled:opacity-70"
          >
            {{ isExporting ? 'Exporting...' : 'Export to TSV' }}
          </button>
        </div>
      </div>

      <!-- Import Modal -->
      <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
          <div class="p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">
              Import Carriers from TSV
            </h2>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
              Upload a tab-separated (.tsv or .txt) file. Expected columns (in order):
            </p>

            <ul class="list-disc pl-5 mb-6 text-sm text-gray-600 dark:text-gray-400">
              <li>short_code</li>
              <li>name</li>
              <li>contact_name</li>
              <li>email (semicolon delimited if multiple)</li>
              <li>phone</li>
              <li>address</li>
              <li>city</li>
              <li>state</li>
              <li>zip</li>
              <li>country</li>
              <li>is_active (1/0, true/false)</li>
              <li>notes</li>
            </ul>

            <form @submit.prevent="importFile">
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Select TSV File
                </label>
                <input
                  type="file"
                  accept=".tsv,.txt"
                  @change="handleFileChange"
                  class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-600 dark:file:text-gray-200"
                  required
                />
              </div>

              <div class="flex justify-end space-x-3">
                <button
                  type="button"
                  @click="showImportModal = false"
                  class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="!selectedFile || importForm.processing"
                  class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ importForm.processing ? 'Importing...' : 'Import' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
          <input
            v-model="search"
            type="text"
            placeholder="Search by name, code, contact or email..."
            class="w-full border border-gray-300 dark:border-gray-600 rounded-md p-2.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
          />
        </div>

        <div class="flex items-center space-x-3">
          <label class="text-sm text-gray-700 dark:text-gray-300">Items per page:</label>
          <select
            @change="changePerPage"
            :value="carriers.per_page"
            class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
          </select>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="!carriers.data?.length"
        class="mt-12 text-center py-16 bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700"
      >
        <div class="text-gray-500 dark:text-gray-400 text-lg font-medium">
          No carriers found
        </div>
        <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
          Get started by adding a new carrier above.
        </p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-t-lg overflow-hidden shadow-md dark:shadow-gray-900/30">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-left">
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Short Code</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Name</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Contact Name</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Emails</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Phone</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Active</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="carrier in carriers.data" :key="carrier.id">
              <td class="px-6 py-4 text-gray-900 dark:text-gray-100 font-medium">
                {{ carrier.short_code }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                {{ carrier.name }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                {{ carrier.contact_name || '—' }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400 group relative cursor-help">
                <span :title="getEmailInfo(carrier.emails).tooltip">
                  {{ getEmailInfo(carrier.emails).count }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                {{ carrier.phone || '—' }}
              </td>
              <td class="px-6 py-4 text-center">
                <span :class="carrier.is_active ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 dark:text-red-400 font-medium'">
                  {{ carrier.is_active ? 'Yes' : 'No' }}
                </span>
              </td>
              <td class="px-6 py-4 text-center space-x-5">
                <a
                  :href="route('admin.carriers.edit', carrier.id)"
                  class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                  title="Edit Carrier"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </a>

                <button
                  v-if="hasAdminAccess"
                  @click="destroy(carrier.id)"
                  class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                  title="Delete Carrier"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination – restyled to match app-wide style -->
      <div v-if="carriers.data?.length" class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-b-lg">
        <!-- Showing info -->
        <div class="text-sm text-gray-700 dark:text-gray-300 mb-4 sm:mb-0">
          Showing {{ carriers.from ?? 0 }}–{{ carriers.to ?? 0 }} of {{ carriers.total }} entries
        </div>

        <!-- Pagination buttons -->
        <div class="flex flex-wrap items-center gap-1 sm:gap-2">
          <!-- Page numbers -->
          <template v-for="(link, index) in carriers.links" :key="index">
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
  </AdminLayout>
</template>
