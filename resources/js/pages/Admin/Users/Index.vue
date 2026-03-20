<script setup lang="ts">
import AdminLayout from '@/layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'
import { Confirm, Notify } from 'notiflix'

const props = defineProps<{
  users: Array<{
    id: number
    name: string
    email: string
    roles: string[]
    is_active: boolean
    deleted_at: string | null
    edit_url: string
    disable_url: string
    delete_url: string
    restore_url: string | null
  }>
  flash?: {
    success?: string
    error?: string
  }
}>()

const page = usePage()

// Show flashed messages on load
onMounted(() => {
  if (page.props.flash?.success) Notify.success(page.props.flash.success)
  if (page.props.flash?.error) Notify.failure(page.props.flash.error)
})

// ── Export Users ─────────────────────────────────────────────────────────────
const exportUsers = () => {
  window.location.href = route('admin.users.export')
}

// ── Import Users ─────────────────────────────────────────────────────────────
const importInput = ref<HTMLInputElement | null>(null)

const triggerImport = () => {
  importInput.value?.click()
}

const handleImport = (event: Event) => {
  const fileInput = event.target as HTMLInputElement
  const file = fileInput.files?.[0]

  if (!file) return

  Confirm.show(
    'Import Users',
    'This will update existing users by email and add new ones. Roles will be synced. Continue?',
    'Yes, import',
    'Cancel',
    () => {
      const formData = new FormData()
      formData.append('file', file)

      router.post(route('admin.users.import'), formData, {
        onSuccess: () => {
          Notify.success('Users imported successfully!')
          router.reload({ only: ['users', 'flash'] }) // refresh table
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

// ── Disable User ──────────────────────────────────────────────────────────────
const disableUser = (user: (typeof props.users)[number]) => {
  Confirm.show(
    'Disable User',
    `Are you sure you want to disable ${user.name}? They will not be able to log in.`,
    'Yes, disable',
    'Cancel',
    () => {
      router.patch(user.disable_url, {}, {
        onSuccess: () => {
          Notify.success(`${user.name} has been disabled.`)
          router.reload({ only: ['users', 'flash'] })
        },
        onError: () => {
          Notify.failure('Failed to disable user.')
        },
      })
    },
    () => {},
    {
      okButtonBackground: '#f97316',
      titleColor: '#111827',
    }
  )
}

// ── Enable User ───────────────────────────────────────────────────────────────
const enableUser = (user: (typeof props.users)[number]) => {
  router.patch(route('admin.users.enable', user.id), {}, {
    onSuccess: () => {
      Notify.success(`${user.name} has been enabled.`)
      router.reload({ only: ['users', 'flash'] })
    },
    onError: () => {
      Notify.failure('Failed to enable user.')
    },
  })
}

// ── Delete User (Soft Delete) ─────────────────────────────────────────────────
const deleteUser = (user: (typeof props.users)[number]) => {
  Confirm.show(
    'Delete User',
    `Are you sure you want to delete ${user.name}? This action can be undone.`,
    'Yes, delete',
    'Cancel',
    () => {
      router.delete(user.delete_url, {}, {
        onSuccess: () => {
          Notify.success(`${user.name} has been deleted.`)
          router.reload({ only: ['users', 'flash'] })
        },
        onError: () => {
          Notify.failure('Failed to delete user.')
        },
      })
    },
    () => {},
    {
      okButtonBackground: '#dc2626',
      titleColor: '#111827',
    }
  )
}

// ── Restore User ──────────────────────────────────────────────────────────────
const restoreUser = (user: (typeof props.users)[number]) => {
  if (!user.restore_url) return

  Confirm.show(
    'Restore User',
    `Are you sure you want to restore ${user.name}?`,
    'Yes, restore',
    'Cancel',
    () => {
      router.patch(user.restore_url, {}, {
        onSuccess: () => {
          Notify.success(`${user.name} has been restored.`)
          router.reload({ only: ['users', 'flash'] })
        },
        onError: () => {
          Notify.failure('Failed to restore user.')
        },
      })
    },
    () => {},
    {
      okButtonBackground: '#10b981',
      titleColor: '#111827',
    }
  )
}
</script>

<template>
  <Head title="Manage Users" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Users Management
        </h1>

        <div class="flex flex-wrap gap-3">
          <!-- Export Button -->
          <button
            @click="exportUsers"
            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export Users (CSV)
          </button>

          <!-- Import Button -->
          <label
            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm cursor-pointer transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
            </svg>
            Import Users (CSV)
            <input
              ref="importInput"
              type="file"
              accept=".csv,.txt"
              class="hidden"
              @change="handleImport"
            />
          </label>

          <!-- Optional: Create User button if you have one -->
          <!-- <Link :href="route('admin.users.create')" class="...">Create User</Link> -->
        </div>
      </div>

      <!-- Flash message display (optional redundancy if not using Notify) -->
      <div v-if="page.props.flash?.success" class="mb-6 p-4 bg-green-100 border border-green-200 text-green-800 rounded-lg">
        {{ page.props.flash.success }}
      </div>

      <!-- Users Table -->
      <div class="overflow-x-auto rounded-lg shadow dark:shadow-gray-900/30">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Name
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Email
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Roles
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Status
              </th>
              <th class="px-6 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="user in users" :key="user.id" :class="{ 'opacity-60': user.deleted_at || !user.is_active }">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                {{ user.name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ user.email }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ user.roles.join(', ') || 'None' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <span v-if="user.deleted_at" class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100">
                  Deleted
                </span>
                <span v-else-if="!user.is_active" class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-100">
                  Disabled
                </span>
                <span v-else class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100">
                  Active
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <!-- Edit Button -->
                <a
                  :href="user.edit_url"
                  class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                  title="Edit User"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </a>

                <!-- Disable/Enable Button -->
                <button
                  v-if="!user.deleted_at && user.is_active"
                  @click="disableUser(user)"
                  class="text-orange-600 hover:text-orange-800 dark:text-orange-400 dark:hover:text-orange-300 transition-colors"
                  title="Disable User"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-4.803m5.596-3.856a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>
                <button
                  v-else-if="!user.deleted_at && !user.is_active"
                  @click="enableUser(user)"
                  class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                  title="Enable User"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>

                <!-- Delete Button -->
                <button
                  v-if="!user.deleted_at"
                  @click="deleteUser(user)"
                  class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                  title="Delete User"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>

                <!-- Restore Button -->
                <button
                  v-else-if="user.deleted_at && user.restore_url"
                  @click="restoreUser(user)"
                  class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 transition-colors"
                  title="Restore User"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                </button>
              </td>
            </tr>

            <tr v-if="!users.length">
              <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                No users found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>
