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
    edit_url: string
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
              <th class="px-6 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="user in users" :key="user.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                {{ user.name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ user.email }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ user.roles.join(', ') || 'None' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <a
                  :href="user.edit_url"
                  class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                  title="Edit User"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </a>
              </td>
            </tr>

            <tr v-if="!users.length">
              <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                No users found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>
