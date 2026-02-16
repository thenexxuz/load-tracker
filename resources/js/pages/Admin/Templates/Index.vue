<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { Confirm, Notify } from 'notiflix';
import { onMounted } from 'vue';

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
    () => {
      // Cancelled - do nothing
    },
    {
      titleColor: '#ff0000',
      okButtonBackground: '#ff0000',
    }
  )

  if (result.isConfirmed) {
    router.delete(route('admin.templates.destroy', id), {
      preserveScroll: true,
      onSuccess: () => {
        Notify.success('Template has been deleted.')
      },
      onError: () => {
        Notify.failure('Failed to delete template.')
      }
    })
  }
}

const goToShow = (id: number) => {
  router.visit(route('admin.templates.show', id))
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
    route('admin.templates.index'),
    { search: search.value || null, per_page: value, page: 1 },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

onMounted(() => {
  if (page.props.flash?.success) {
    Notify.success(page.props.flash.success)
  }
  if (page.props.flash?.error) {
    Notify.failure(page.props.flash.error)
  }
  if (page.props.flash?.info) {
    Notify.info(page.props.flash.info)
  }
  if (page.props.flash?.warning) {
    Notify.warning(page.props.flash.warning)
  }
})
</script>

<template>
  <Head title="Manage Templates" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Template Management
        </h1>
        <a
          :href="route('admin.templates.create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
        >
          Create New Template
        </a>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-t-lg overflow-hidden shadow-md dark:shadow-gray-900/30">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-left">
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Name</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Related To</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 text-center">Actions</th>
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
                <span v-else>—</span>
              </td>
              <td class="px-6 py-4 text-center space-x-5" @click.stop>
                <a
                  :href="route('admin.templates.edit', template.id)"
                  class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                  title="Edit Template"
                  @click.stop
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </a>

                <button
                  @click.stop="destroy(template.id)"
                  class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                  title="Delete Template"
                >
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                  </svg>
                </button>
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="!templates.data?.length">
              <td colspan="5" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400 text-lg font-medium">
                No templates found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination – restyled to match app-wide style -->
      <div v-if="templates.data?.length" class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-b-lg">
        <!-- Showing info -->
        <div class="text-sm text-gray-700 dark:text-gray-300 mb-4 sm:mb-0">
          Showing {{ templates.from ?? 0 }}–{{ templates.to ?? 0 }} of {{ templates.total }} entries
        </div>

        <!-- Pagination buttons -->
        <div class="flex flex-wrap items-center gap-1 sm:gap-2">
          <!-- Page numbers -->
          <template v-for="(link, index) in templates.links" :key="index">
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
