<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3'
import { onMounted, ref, watch, computed } from 'vue'
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
const selectedNotifications = ref<Set<string>>(new Set())
const isProcessing = ref<boolean>(false)

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

const goToNotification = (id: string, event: MouseEvent): void => {
  // Don't navigate if clicking the checkbox
  if ((event.target as HTMLElement).tagName === 'INPUT') {
    return
  }
  router.visit(route('notifications.show', id))
}

const toggleSelection = (id: string): void => {
  if (selectedNotifications.value.has(id)) {
    selectedNotifications.value.delete(id)
  } else {
    selectedNotifications.value.add(id)
  }
}

const selectAll = (): void => {
  if (selectedNotifications.value.size === props.notifications.data.length) {
    selectedNotifications.value.clear()
  } else {
    props.notifications.data.forEach(notification => {
      selectedNotifications.value.add(notification.id)
    })
  }
}

const isAllSelected = computed<boolean>(() => {
  return props.notifications.data.length > 0 && selectedNotifications.value.size === props.notifications.data.length
})

const someSelected = computed<boolean>(() => {
  return selectedNotifications.value.size > 0 && selectedNotifications.value.size < props.notifications.data.length
})

const selectedCount = computed<number>(() => {
  return selectedNotifications.value.size
})

const bulkMarkAsRead = async (): Promise<void> => {
  if (selectedNotifications.value.size === 0) return

  isProcessing.value = true
  try {
    await fetch(route('notifications.bulk-update'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        notification_ids: Array.from(selectedNotifications.value),
        action: 'mark_read',
      }),
    }).then(response => {
      if (response.ok) {
        Notify.success(`${selectedNotifications.value.size} notification${selectedNotifications.value.size !== 1 ? 's' : ''} marked as read`)
        router.get(route('notifications.index'), {
          show_read: showRead.value ? 1 : 0,
        }, {
          preserveScroll: true,
          replace: true,
        })
        selectedNotifications.value.clear()
      } else {
        Notify.failure('Failed to mark notifications as read')
      }
    })
  } catch (error) {
    Notify.failure('An error occurred')
    console.error(error)
  } finally {
    isProcessing.value = false
  }
}

const bulkMarkAsUnread = async (): Promise<void> => {
  if (selectedNotifications.value.size === 0) return

  isProcessing.value = true
  try {
    await fetch(route('notifications.bulk-update'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        notification_ids: Array.from(selectedNotifications.value),
        action: 'mark_unread',
      }),
    }).then(response => {
      if (response.ok) {
        Notify.success(`${selectedNotifications.value.size} notification${selectedNotifications.value.size !== 1 ? 's' : ''} marked as unread`)
        router.get(route('notifications.index'), {
          show_read: showRead.value ? 1 : 0,
        }, {
          preserveScroll: true,
          replace: true,
        })
        selectedNotifications.value.clear()
      } else {
        Notify.failure('Failed to mark notifications as unread')
      }
    })
  } catch (error) {
    Notify.failure('An error occurred')
    console.error(error)
  } finally {
    isProcessing.value = false
  }
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

      <!-- Bulk Actions Bar -->
      <div
        v-if="selectedCount > 0"
        class="mb-4 flex items-center justify-between gap-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4"
      >
        <div class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
          <span>{{ selectedCount }} notification{{ selectedCount !== 1 ? 's' : '' }} selected</span>
        </div>
        <div class="flex gap-2">
          <button
            :disabled="isProcessing"
            @click="bulkMarkAsRead"
            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="!isProcessing">Mark as Read</span>
            <span v-else>Processing...</span>
          </button>
          <button
            :disabled="isProcessing"
            @click="bulkMarkAsUnread"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="!isProcessing">Mark as Unread</span>
            <span v-else>Processing...</span>
          </button>
          <button
            :disabled="isProcessing"
            @click="() => selectedNotifications.clear()"
            class="rounded-md bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Clear Selection
          </button>
        </div>
      </div>

      <!-- Notifications Table -->
      <div class="overflow-x-auto rounded-t-lg shadow">
        <table class="min-w-full bg-white dark:bg-gray-800">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-left">
              <th class="px-4 py-4 font-medium text-gray-700 dark:text-gray-300 w-12">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="someSelected"
                  @change="selectAll"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                />
              </th>
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
                'hover:bg-gray-50 dark:hover:bg-gray-700/50',
                !isRead(notification.read_at) ? 'bg-blue-50 dark:bg-blue-900/20' : '',
              ]"
              @click="goToNotification(notification.id, $event)"
            >
              <td class="px-4 py-4 text-center">
                <input
                  type="checkbox"
                  :checked="selectedNotifications.has(notification.id)"
                  @change="toggleSelection(notification.id)"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                />
              </td>
              <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100 cursor-pointer">
                {{ notification.data.subject }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400 cursor-pointer">
                {{ formatDateTime(notification.created_at) }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400 cursor-pointer">
                {{ notification.read_at ? formatDateTime(notification.read_at) : '—' }}
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
              <td colspan="5" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
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
