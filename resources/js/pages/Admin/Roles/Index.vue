<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { onMounted, ref, inject } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'
import ActionIconButton from '@/components/ActionIconButton.vue'
import { Notify, Confirm } from 'notiflix'

const route = inject('route')!

const page = usePage()

const props = defineProps<{
  roles: any[]
  permissions: string[]
}>()

// Create form
const form = useForm({
  name: '',
  permissions: [] as string[]
})

const successMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

// Submit create form
const submit = () => {
  successMessage.value = null
  errorMessage.value = null

  form.post(route('admin.roles.store'), {
    onSuccess: () => {
      form.reset('name', 'permissions')
      successMessage.value = 'Role created successfully!'
    },
    onError: (errors) => {
      errorMessage.value = 'Please fix the errors below.'
      console.log('Form errors:', errors)
    },
    onFinish: () => {
      form.processing = false
    }
  })
}

// ── Edit Modal ──────────────────────────────────────────────
const showEditModal = ref(false)
const editingRole = ref<any | null>(null)
const editForm = useForm({
  name: '',
  permissions: [] as string[]
})

const openEditModal = (role: any) => {
  editingRole.value = role
  editForm.name = role.name
  editForm.permissions = role.permissions.map((p: any) => p.name)
  showEditModal.value = true
}

const submitEdit = () => {
  if (!editingRole.value) return

  editForm.put(route('admin.roles.update', editingRole.value.id), {
    onSuccess: () => {
      showEditModal.value = false
      editingRole.value = null
      successMessage.value = 'Role updated successfully!'
    },
    onError: (errors) => {
      errorMessage.value = 'Please fix the errors below.'
      console.log('Edit errors:', errors)
    },
    onFinish: () => {
      editForm.processing = false
    }
  })
}

const cancelEdit = () => {
  showEditModal.value = false
  editingRole.value = null
  editForm.reset()
}

// ── Delete Modal ────────────────────────────────────────────
const showDeleteModal = ref(false)
const roleToDelete = ref<any | null>(null)

const openDeleteModal = (role: any) => {
  roleToDelete.value = role
  showDeleteModal.value = true
}

const confirmDelete = () => {
  if (!roleToDelete.value) return

  router.delete(route('admin.roles.destroy', roleToDelete.value.id), {
    onSuccess: () => {
      showDeleteModal.value = false
      roleToDelete.value = null
      successMessage.value = 'Role deleted successfully!'
    },
    onError: (errors) => {
      errorMessage.value = 'Failed to delete role. It may be in use.'
      console.error('Delete error:', errors)
    },
    preserveScroll: true,
  })
}

const cancelDelete = () => {
  showDeleteModal.value = false
  roleToDelete.value = null
}

// ── Export Roles ─────────────────────────────────────────────────────────────
const exportRoles = () => {
  window.location.href = route('admin.roles.export')
}

// ── Import Roles ─────────────────────────────────────────────────────────────
const importInput = ref<HTMLInputElement | null>(null)

const triggerImport = () => {
  importInput.value?.click()
}

const handleImport = (event: Event) => {
  const fileInput = event.target as HTMLInputElement
  const file = fileInput.files?.[0]

  if (!file) return

  Confirm.show(
    'Import Roles',
    'This will update existing roles by name and add new ones. Continue?',
    'Yes, import',
    'Cancel',
    () => {
      const formData = new FormData()
      formData.append('file', file)

      router.post(route('admin.roles.import'), formData, {
        onSuccess: () => {
          Notify.success('Roles imported successfully!')
          router.reload({ only: ['roles', 'flash'] }) // refresh table
          fileInput.value = '' // reset input
        },
        onError: (errors) => {
          Notify.failure(errors.file || 'Import failed. Please check the file format.')
        },
      })
    },
    () => {
      fileInput.value = '' // reset on cancel
    },
    {
      okButtonBackground: '#10b981',
      titleColor: '#111827',
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
  <Head title="Manage Roles" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Roles Management
        </h1>

        <div class="flex flex-wrap gap-3">
          <!-- Export Button -->
          <button
            @click="exportRoles"
            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export Roles (CSV)
          </button>

          <!-- Import Button -->
          <label
            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm cursor-pointer transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
            </svg>
            Import Roles (CSV)
            <input
              ref="importInput"
              type="file"
              accept=".csv,.txt"
              class="hidden"
              @change="handleImport"
            />
          </label>
        </div>
      </div>

      <!-- Messages -->
      <div v-if="successMessage" class="mb-6 p-4 bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200 rounded-lg">
        {{ successMessage }}
      </div>
      <div v-if="errorMessage || Object.keys(form.errors).length || Object.keys(editForm.errors).length" class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg">
        {{ errorMessage || 'Please check the form for errors.' }}
      </div>

      <!-- Create Role Form -->
      <form
        @submit.prevent="submit"
        class="mb-10 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700"
      >
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Role Name
          </label>
          <input
            v-model="form.name"
            type="text"
            :class="[
              'border p-2 w-full rounded-md focus:ring-2 focus:border-blue-500 focus:outline-none',
              form.errors.name
                ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100'
            ]"
            required
            placeholder="e.g. manager"
          />
          <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ form.errors.name }}
          </p>
        </div>

        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Permissions
          </label>
          <div class="grid grid-cols-3 gap-3">
            <label
              v-for="perm in permissions"
              :key="perm"
              class="flex items-center space-x-2 cursor-pointer"
            >
              <input
                type="checkbox"
                v-model="form.permissions"
                :value="perm"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700"
              />
              <span class="text-sm text-gray-700 dark:text-gray-300">{{ perm }}</span>
            </label>
          </div>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white px-5 py-2.5 rounded-md font-medium transition-colors disabled:opacity-50"
        >
          {{ form.processing ? 'Creating...' : 'Create Role' }}
        </button>
      </form>

      <!-- Roles Table -->
      <div class="overflow-x-auto rounded-lg shadow dark:shadow-gray-900/30">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Name
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Permissions
              </th>
              <th class="px-6 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="role in roles" :key="role.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                {{ role.name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ role.permissions.map((p: any) => p.name).join(', ') || 'None' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right space-x-4">
                <!-- Edit -->
                <ActionIconButton
                  action="edit"
                  title="Edit Role"
                  @click="openEditModal(role)"
                />

                <!-- Delete -->
                <ActionIconButton
                  action="delete"
                  title="Delete Role"
                  @click="openDeleteModal(role)"
                />
              </td>
            </tr>

            <tr v-if="!roles.length">
              <td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                No roles found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Edit Modal (unchanged) -->
      <div
        v-if="showEditModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm transition-opacity duration-300"
        @click.self="cancelEdit"
      >
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-md w-full mx-4 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            Edit Role
          </h3>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Role Name
            </label>
            <input
              v-model="editForm.name"
              type="text"
              :class="[
                'border p-2 w-full rounded-md focus:ring-2 focus:outline-none',
                editForm.errors.name
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100'
              ]"
              required
            />
            <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ editForm.errors.name }}
            </p>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Permissions
            </label>
            <div class="grid grid-cols-3 gap-3">
              <label
                v-for="perm in props.permissions"
                :key="perm"
                class="flex items-center space-x-2 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :value="perm"
                  v-model="editForm.permissions"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700"
                />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ perm }}</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end space-x-3">
            <button
              @click="cancelEdit"
              class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="submitEdit"
              :disabled="editForm.processing"
              class="px-4 py-2 bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800 text-white rounded-md disabled:opacity-50 transition-colors"
            >
              {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal (unchanged) -->
      <div
        v-if="showDeleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm transition-opacity duration-300"
        @click.self="cancelDelete"
      >
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-md w-full mx-4 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            Confirm Deletion
          </h3>
          <p class="text-gray-600 dark:text-gray-300 mb-6">
            Are you sure you want to delete the role
            <span class="font-medium text-red-600 dark:text-red-400">"{{ roleToDelete?.name }}"</span>?<br />
            This action cannot be undone.
          </p>

          <div class="flex justify-end space-x-3">
            <button
              @click="cancelDelete"
              class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="confirmDelete"
              class="px-4 py-2 bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800 text-white rounded-md disabled:opacity-50 transition-colors"
            >
              Delete Role
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
