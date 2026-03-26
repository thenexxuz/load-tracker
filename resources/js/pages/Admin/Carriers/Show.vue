<script setup lang="ts">
import { Head, router, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'
import ActionIconButton from '@/components/ActionIconButton.vue'
import NotesSection from '@/components/NotesSection.vue'
import { Notify, Confirm } from 'notiflix'

const props = defineProps<{
  carrier: {
    id: number
    short_code: string
    wt_code: string | null
    name: string
    emails: string
    is_active: boolean
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
  activeTrailerAssignments: Array<{
    id: string
    trailer_number: string | null
    shipment_number: string | null
    bol: string | null
    pickup_location_name: string | null
    pickup_location_short_code: string | null
    is_assigned_to_shipment: boolean
  }>
}>()

const page = usePage()
const { auth } = page.props
const userRoles = auth?.user?.roles || []
const hasAdminAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')

// Safe formatted dates
const formattedCreatedAt = computed(() => {
  return new Date(props.carrier.created_at).toLocaleString()
})

const formattedUpdatedAt = computed(() => {
  return new Date(props.carrier.updated_at).toLocaleString()
})

const copyEmails = async () => {
  if (!props.carrier.emails?.trim()) {
    Notify.warning('No emails to copy.')
    return
  }

  const emailsText = props.carrier.emails.trim()  // already comma-separated or line-separated

  try {
    await navigator.clipboard.writeText(emailsText)
    Notify.success('Emails copied to clipboard!', {
      timeout: 3000,
      position: 'right-top',
    })
  } catch (err) {
    console.error('Clipboard copy failed:', err)
    Notify.failure('Failed to copy emails. Please try manually.')
  }
}

// Flash notifications (global style)
onMounted(() => {
  if (page.props.flash?.success) Notify.success(page.props.flash.success)
  if (page.props.flash?.error) Notify.failure(page.props.flash.error)
  if (page.props.flash?.info) Notify.info(page.props.flash.info)
  if (page.props.flash?.warning) Notify.warning(page.props.flash.warning)
})
</script>

<template>
  <Head title="Carrier Details" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Carrier: {{ carrier.name }}
        </h1>
        <div class="flex items-center gap-4">
          <ActionIconButton
            action="edit"
            :href="route('admin.carriers.edit', carrier.id)"
            title="Edit Carrier"
          />
          <a href="javascript:history.back()"
             class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors">
            ← Back
          </a>
        </div>
      </div>

      <!-- Carrier Details Card -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <div class="p-6 space-y-6">
          <!-- Basic Info -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Short Code</h3>
              <p class="text-gray-900 dark:text-gray-100 font-medium">{{ carrier.short_code }}</p>
            </div>

            <div>
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">WT Code</h3>
              <p class="text-gray-900 dark:text-gray-100 font-medium">
                {{ carrier.wt_code || '—' }}
              </p>
            </div>

            <div>
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Name</h3>
              <p class="text-gray-900 dark:text-gray-100 font-medium">{{ carrier.name }}</p>
            </div>

            <div>
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Active</h3>
              <span :class="carrier.is_active ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 dark:text-red-400 font-medium'">
                {{ carrier.is_active ? 'Yes' : 'No' }}
              </span>
            </div>
          </div>

          <!-- Emails -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Emails
              </h3>
              <button
                v-if="carrier.emails?.trim()"
                @click="copyEmails"
                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium flex items-center gap-1 transition-colors"
                title="Copy all emails to clipboard"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                Copy
              </button>
            </div>

            <p class="text-gray-900 dark:text-gray-100 whitespace-pre-line">
              {{ carrier.emails ? carrier.emails.split(',').map(e => e.trim()).join('\n') : 'None' }}
            </p>
          </div>

          <!-- Timestamps -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div>
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Created At</h3>
              <p class="text-gray-900 dark:text-gray-100">{{ formattedCreatedAt }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Updated At</h3>
              <p class="text-gray-900 dark:text-gray-100">{{ formattedUpdatedAt }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Trailers Assigned Or Parked At Pickup Locations
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Shows trailers assigned to undelivered shipments and unassigned trailers still parked at a pickup location.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Trailer Number
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Shipment Number
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Assignment
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  BOL
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Pickup Location
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
              <tr v-for="assignment in activeTrailerAssignments" :key="assignment.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ assignment.trailer_number ?? '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  {{ assignment.shipment_number ?? '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                  {{ assignment.is_assigned_to_shipment ? 'Assigned' : 'Unassigned' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                  {{ assignment.bol ?? '—' }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                  {{ assignment.pickup_location_name ?? '—' }}
                  <span v-if="assignment.pickup_location_short_code" class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                    ({{ assignment.pickup_location_short_code }})
                  </span>
                </td>
              </tr>
              <tr v-if="activeTrailerAssignments.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                  No trailers are currently assigned to undelivered shipments or parked at pickup locations.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <NotesSection
        :entity="carrier"
        entity-type="App\Models\Carrier"
        entity-prop-key="carrier"
      />

      <!-- Back -->
      <div class="mt-8 text-center">
        <a :href="route('admin.carriers.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
          ← Back to Carriers List
        </a>
      </div>
    </div>

    <!-- Add Note Modal -->
    <div v-if="showAddNoteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
        <div class="p-6">
          <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">
            Add Note for {{ carrier.name }}
          </h2>

          <form @submit.prevent="addNote">
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Note Content <span class="text-red-600">*</span>
              </label>
              <textarea
                v-model="noteForm.content"
                rows="5"
                required
                class="w-full p-3 border rounded-md bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none resize-y"
                placeholder="Enter your note here..."
              ></textarea>
              <p v-if="noteForm.errors.content" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ noteForm.errors.content }}
              </p>
            </div>

            <!-- Admin Note Checkbox – only for admin/supervisor -->
            <div v-if="hasAdminOrSupervisor" class="mb-6">
              <label class="flex items-center space-x-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="noteForm.is_admin"
                  class="h-5 w-5 text-purple-600 focus:ring-purple-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700"
                />
                <span class="text-gray-700 dark:text-gray-300 font-medium">
                  Mark as Admin Note (visible to supervisors/admins only)
                </span>
              </label>
            </div>

            <div class="flex justify-end space-x-3">
              <button
                type="button"
                @click="showAddNoteModal = false"
                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="noteForm.processing"
                class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ noteForm.processing ? 'Saving...' : 'Add Note' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
