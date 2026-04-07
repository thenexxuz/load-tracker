<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3'
import { onMounted, ref, watch } from 'vue'
import { Notify } from 'notiflix'
import { route } from 'ziggy-js'

import Pagination from '@/components/Pagination.vue'
import AdminLayout from '@/layouts/AppLayout.vue'

interface NotificationData {
  id: string
  created_at: string
  read_at: string | null
  data: {
    subject: string
    message: string
  }
}

const props = defineProps<{
  notifications: {
    data: NotificationData[]
    current_page: number
    last_page: number
    from: number
    to: number
    total: number
    per_page: number
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters?: {
    show_read?: boolean
  }
}>()

const showRead = ref<boolean>(Boolean(props.filters?.show_read))

const formatDate = (dateString: string): string => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

const formatTime = (dateString: string): string => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

const formatDateTime = (dateString: string): string => {
  if (!dateString) return '—'
  return `${formatDate(dateString)} ${formatTime(dateString)}`
}

const isRead = (readAt: string | null): boolean => {
  return readAt !== null
}

const changePage = (url: string): void => {
  router.get(url, {
    show_read: showRead.value ? 1 : 0,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

watch(showRead, (value) => {
  router.get(route('notifications.index'), {
    show_read: value ? 1 : 0,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
})

const goToNotification = (id: string): void => {
  router.visit(route('notifications.show', id))
}

onMounted(() => {
  if (!props.notifications.data.length) {
    Notify.info('No notifications yet', {
      timeout: 3000,
      clickToClose: true,
    })
  }
})
</script>

<template>
  <Head title="Notifications" />

  <AdminLayout>
    <div class="p-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Notifications
        </h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
          You have {{ props.notifications.total }} notification{{ props.notifications.total !== 1 ? 's' : '' }}
        </p>

        <label class="mt-4 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input
            v-model="showRead"
            type="checkbox"
            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          Show read notifications
        </label>
      </div>

      <!-- Notifications Table -->
      <div class="overflow-x-auto rounded-t-lg shadow">
        <table class="min-w-full bg-white dark:bg-gray-800">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-left">
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Subject</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 min-w-[150px]">Received</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 min-w-[150px]">Read</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="notification in notifications.data"
              :key="notification.id"
              :class="[
                'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50',
                !isRead(notification.read_at) ? 'bg-blue-50 dark:bg-blue-900/20' : '',
              ]"
              @click="goToNotification(notification.id)"
            >
              <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                {{ notification.data.subject }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                {{ formatDateTime(notification.created_at) }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                {{ isRead(notification.read_at) ? formatDateTime(notification.read_at) : '—' }}
              </td>
              <td class="px-6 py-4 text-center">
                <span
                  :class="[
                    'inline-block px-3 py-1 rounded-full text-sm font-medium',
                    isRead(notification.read_at)
                      ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300'
                      : 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300',
                  ]"
                >
                  {{ isRead(notification.read_at) ? 'Read' : 'Unread' }}
                </span>
              </td>
            </tr>

            <tr v-if="!notifications.data.length">
              <td colspan="4" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                No notifications found.<br />
                <span class="text-sm">Come back later!</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <Pagination
        :pagination="notifications"
        @pageChange="changePage"
      />
    </div>
  </AdminLayout>
</template>

<style scoped>
/* Add any scoped styles if needed */
</style>
