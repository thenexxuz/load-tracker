<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import Pagination from '@/components/Pagination.vue'
import { Confirm, Notify } from 'notiflix'
import { onMounted } from 'vue'
import { route } from 'ziggy-js'

const page = usePage()

const props = defineProps<{
  scheduledItems: {
    data: Array<{
      id: number
      name: string
      schedule_type: 'daily' | 'weekly' | 'monthly'
      schedule_time: string
      schedule_day_of_week?: number | null
      schedule_day_of_month?: number | null
      template_id: number | null
      template?: { id: number; name: string } | null
      apply_to_all: boolean
      schedulable_type: string
      schedulable_id: number | null
      schedulable?: {
        id: number
        name: string
        short_code: string
      } | null
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
    'Delete Scheduled Item',
    'Are you sure you want to delete this scheduled item? This action cannot be undone.',
    'Yes, delete it',
    'Cancel',
    () => {
      router.delete(route('admin.scheduled-items.destroy', id), {
        onSuccess: () => {
          Notify.success('Scheduled item has been deleted.')
        },
        onError: () => {
          Notify.failure('Failed to delete scheduled item.')
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

const getScheduleLabel = (item: any): string => {
  const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
  
  switch (item.schedule_type) {
    case 'daily':
      return `Daily at ${item.schedule_time}`
    case 'weekly':
      return `Weekly on ${dayNames[item.schedule_day_of_week] || 'Error'} at ${item.schedule_time}`
    case 'monthly':
      return `Monthly on day ${item.schedule_day_of_month} at ${item.schedule_time}`
    default:
      return 'Unknown'
  }
}

const getSchedulableLabel = (item: any): string => {
  if (item.apply_to_all) {
    const type = item.schedulable_type.split('\\').pop()
    if (type === 'Carrier') {
      return 'All Carriers'
    }
    return `All ${type}s`
  }

  if (!item.schedulable) return 'Unknown'

  const type = item.schedulable_type.split('\\').pop()
  if (type === 'Carrier') {
    return `${item.schedulable.short_code} - ${item.schedulable.name}`
  }

  return item.schedulable.name
}

const changePage = (url: string) => {
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  })
}

const changePerPage = (value: number) => {
  router.get(
    route('admin.scheduled-items.index'),
    { per_page: value, page: 1 },
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
  <Head title="Scheduled Items" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Scheduled Email Items
        </h1>
        <Link
          :href="route('admin.scheduled-items.create')"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
        >
          Create New Item
        </Link>
      </div>

      <!-- Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Name
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Schedule
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Target
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Template
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="props.scheduledItems.data.length === 0">
                <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                  No scheduled items found.
                </td>
              </tr>
              <tr v-for="item in props.scheduledItems.data" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ item.name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                  {{ getScheduleLabel(item) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                  {{ getSchedulableLabel(item) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                  {{ item.template?.name || '—' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                  <Link
                    :href="route('admin.scheduled-items.edit', item.id)"
                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                  >
                    Edit
                  </Link>
                  <button
                    @click="destroy(item.id)"
                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <Pagination
        :pagination="props.scheduledItems"
        @pageChange="changePage"
        @perPageChange="changePerPage"
      />
    </div>
  </AdminLayout>
</template>
