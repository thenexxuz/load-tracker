<!-- resources/js/components/NotesSection.vue -->
<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Notify, Confirm } from 'notiflix'
import { usePage } from '@inertiajs/vue3'

const props = defineProps<{
  entity: {
    id: number
    [key: string]: any // e.g. shipment_number, name, etc.
  }
  entityType: string // 'App\\Models\\Shipment', 'App\\Models\\Carrier', etc.
  entityPropKey: string // 'shipment', 'carrier', etc. — used in reload({ only: [...] })
}>()

const { auth } = usePage().props
const userRoles = auth?.user?.roles || []
const hasAdminAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')

const showAddNoteModal = ref(false)
const noteForm = useForm({
  content: '',
  is_admin: false,
  notable_id: props.entity.id,
  notable_type: props.entityType,
})

const addNote = () => {
  noteForm.post(route('admin.notes.store'), {
    preserveState: false,
    preserveScroll: true,
    onSuccess: () => {
      showAddNoteModal.value = false
      noteForm.reset()
      router.reload({ only: [props.entityPropKey], preserveScroll: true })
    },
    onError: (errors) => {
      console.error('Note errors:', errors)
    },
  })
}

const deleteNote = async (noteId: number) => {
  const confirmed = await Confirm.show(
    'Delete Note?',
    'This action cannot be undone.',
    'Yes, delete',
    'Cancel',
    () => {
        router.delete(route('admin.notes.destroy', noteId), {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => {
            router.reload({
                only: [props.entityPropKey],
                preserveScroll: true,
            })
            },
            onError: (errors) => {
                console.error('Note errors:', errors)
            },
        })
    },
    () => false,
    {
      titleColor: '#ff0000',
      okButtonBackground: '#ff0000',
    }
  )
}

const showNote = (isAdminNote: boolean) => {
  return !isAdminNote || hasAdminAccess
}
</script>

<template>
  <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="p-6 border-b dark:border-gray-700 flex justify-between items-center">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
        Notes
      </h2>
      <button
        @click="showAddNoteModal = true"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors"
      >
        + Add Note
      </button>
    </div>

    <div class="p-6">
      <div v-if="!entity.notes?.length" class="text-center py-8 text-gray-500 dark:text-gray-400">
        No notes yet.
      </div>

      <div v-else class="space-y-4">
        <div v-for="note in entity.notes" :key="note.id">
          <div
            v-if="showNote(note.is_admin)"
            class="p-4 bg-gray-50 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700"
          >
            <div class="flex justify-between items-start mb-2">
              <div class="flex-1">
                <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">
                  {{ note.content }}
                </p>
              </div>
              <div class="text-right text-xs text-gray-500 dark:text-gray-400 ml-4">
                <div v-if="note.is_admin" class="text-purple-600 dark:text-purple-400 font-semibold">
                  Admin Note
                </div>
                <div>
                  {{ note.user?.name || 'System' }}
                </div>
                <div>
                  {{ new Date(note.created_at).toLocaleString() }}
                </div>
              </div>
            </div>

            <div v-if="hasAdminAccess" class="mt-2 text-right">
              <button
                @click="deleteNote(note.id)"
                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm transition-colors"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Note Modal -->
  <div v-if="showAddNoteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
      <div class="p-6">
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

          <div v-if="hasAdminAccess" class="mb-6">
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
</template>
