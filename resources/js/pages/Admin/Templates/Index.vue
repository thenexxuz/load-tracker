<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { Confirm, Notify } from 'notiflix'
import { onMounted, ref } from 'vue'

import ActionIconButton from '@/components/ActionIconButton.vue'
import Pagination from '@/components/Pagination.vue'
import AdminLayout from '@/layouts/AppLayout.vue'

const page = usePage()

const props = defineProps<{
  templates: {
    data: Array<{
      id: number
      name: string
      model_type: string
      model: {
        id: number
        name?: string
        short_code?: string
      } | null
      subject: string | null
      message: string | null
      created_at: string
      updated_at: string
    }>
    current_page: number
    last_page: number
    from: number
    to: number
    total: number
    per_page: number
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
}>()

// ── Export Templates ──────────────────────────────────────────────────────────
const exportTemplates = () => {
  window.location.href = route('admin.templates.export')
}

// ── Import Templates ──────────────────────────────────────────────────────────
const importInput = ref<HTMLInputElement | null>(null)

const triggerImport = () => {
  importInput.value?.click()
}

const handleImport = (event: Event) => {
  const fileInput = event.target as HTMLInputElement
  const file = fileInput.files?.[0]

  if (!file) return

  Confirm.show(
    'Import Templates',
    'This will update existing templates by name and add new ones. Existing content/subject will be overwritten. Continue?',
    'Yes, import',
    'Cancel',
    () => {
      const formData = new FormData()
      formData.append('file', file)

      router.post(route('admin.templates.import'), formData, {
        onSuccess: () => {
          Notify.success('Templates imported successfully!')
          router.reload({ only: ['templates', 'flash'] }) // refresh table
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

// ── Existing destroy function ────────────────────────────────────────────────
const destroy = async (id: number) => {
  const result = await Confirm.show(
    'Delete Template',
    'Are you sure you want to delete this template? This action cannot be undone.',
    'Yes, delete it',
    'Cancel',
    () => {
      router.delete(route('admin.templates.destroy', id), {
        onSuccess: () => {
          Notify.success('Template has been deleted.')
        },
        onError: () => {
          Notify.failure('Failed to delete template.')
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

const goToShow = (id: number) => {
  router.visit(route('admin.templates.show', id))
}

const changePage = (url: string) => {
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  })
}

const changePerPage = (value: number) => {
  router.get(
    route('admin.templates.index'),
    { search: search.value || null, per_page: value, page: 1 },
    { preserveState: true, preserveScroll: true, replace: true }
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
  <Head title="Manage Templates" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Template Management
        </h1>

        <div class="flex flex-wrap gap-3">
          <!-- Export Button -->
          <button
            @click="exportTemplates"
            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export Templates (CSV)
          </button>

          <!-- Import Button -->
          <label
            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm cursor-pointer transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
            </svg>
            Import Templates (CSV)
            <input
              ref="importInput"
              type="file"
              accept=".csv,.txt"
              class="hidden"
              @change="handleImport"
            />
          </label>

          <!-- Create New Template Button -->
          <a
            :href="route('admin.templates.create')"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
          >
            Create New Template
          </a>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-lg shadow dark:shadow-gray-900/30">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Name
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Related To
              </th>
              <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="template in templates.data"
              :key="template.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
              @click="goToShow(template.id)"
            >
              <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                {{ template.name }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                <span v-if="template.model">
                  {{ template.model_type.split('\\').pop() }}:
                  {{ template.model.short_code || template.model.name || 'ID ' + template.model.id }}
                </span>
                <span v-else-if="template.model_type === 'App\\Models\\Template'">
                  Template Token
                </span>
                <span v-else>—</span>
              </td>
              <td class="px-6 py-4 text-center space-x-5" @click.stop>
                <ActionIconButton
                  action="edit"
                  :href="route('admin.templates.edit', template.id)"
                  title="Edit Template"
                  stop
                />

                <ActionIconButton
                  action="delete"
                  title="Delete Template"
                  stop
                  @click="destroy(template.id)"
                />
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="!templates.data?.length">
              <td colspan="3" class="px-6 py-16 text-center text-sm text-gray-500 dark:text-gray-400 text-lg font-medium">
                No templates found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <Pagination
        :pagination="templates"
        @pageChange="changePage"
        @perPageChange="changePerPage"
      />
    </div>
  </AdminLayout>
</template>
