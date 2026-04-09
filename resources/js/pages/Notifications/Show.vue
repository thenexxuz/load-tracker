<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import AdminLayout from '@/layouts/AppLayout.vue'

interface NotificationData {
  id: string
  subject: string
  message: string
  html_message: string | null
  created_at: string
  read_at: string | null
}

const props = defineProps<{
  notification: NotificationData
}>()

const formatDateTime = (dateString: string): string => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

const isRead = (): boolean => {
  return props.notification.read_at !== null
}
</script>

<template>
  <Head :title="notification.subject" />

  <AdminLayout>
    <div class="p-6">
      <div class="mb-6">
        <Link
          :href="route('notifications.index')"
          class="inline-flex items-center text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 mb-4"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Back to Notifications
        </Link>

        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
          {{ notification.subject }}
        </h1>

        <div class="flex items-center gap-4 flex-wrap">
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600 dark:text-gray-400">Received:</span>
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
              {{ formatDateTime(notification.created_at) }}
            </span>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600 dark:text-gray-400">Read:</span>
            <span
              v-if="isRead()"
              class="text-sm font-medium text-gray-900 dark:text-gray-100"
            >
              {{ formatDateTime(notification.read_at) }}
            </span>
            <span
              v-else
              class="inline-block px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300"
            >
              Unread
            </span>
          </div>

          <div class="flex items-center gap-2">
            <span
              :class="[
                'inline-block px-3 py-1 rounded-full text-sm font-medium',
                isRead()
                  ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300'
                  : 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300',
              ]"
            >
              {{ isRead() ? 'Read' : 'Unread' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Notification Content -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8">
        <div
          v-if="notification.html_message"
          class="prose dark:prose-invert max-w-none"
          v-html="notification.html_message"
        />
        <div
          v-else
          class="prose dark:prose-invert max-w-none whitespace-pre-wrap"
        >
          {{ notification.message }}
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
/* Add any scoped styles if needed */

:deep(.prose) {
  font-size: 1rem;
  line-height: 1.6;
  color: #374151;
}

:deep(.dark .prose) {
  color: #d1d5db;
}
</style>
