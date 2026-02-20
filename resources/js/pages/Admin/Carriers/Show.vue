<script setup lang="ts">
import { Head, router, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'
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
        <div class="space-x-4">
          <a :href="route('admin.carriers.edit', carrier.id)"
             class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 font-medium transition-colors">
            Edit
          </a>
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
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Emails</h3>
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

      <NotesSection
        :entity="carrier"
        entity-type="App\Models\Carrier"
        entity-prop-key="carrier"
      />

      <!-- Back -->
      <div class="mt-8 text-center">
        <a href="javascript:history.back()" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
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
