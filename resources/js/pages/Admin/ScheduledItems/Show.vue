<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import ActionIconButton from '@/components/ActionIconButton.vue'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  scheduledItem: {
    id: number
    name: string
    schedule_type: 'daily' | 'weekly' | 'monthly'
    schedule_time: string
    schedule_day_of_week?: number | null
    schedule_day_of_month?: number | null
    template_id: number | null
    template?: {
      id: number
      name: string
      model_type: string
      subject: string | null
      message: string | null
    } | null
    apply_to_all: boolean
    schedulable_type: string
    schedulable_id: number | null
    schedulable?: {
      id: number
      name: string
      short_code?: string
      guid?: string
    } | null
    created_at: string
    updated_at: string
  }
}>()

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

const getScheduleLabel = (): string => {
  const item = props.scheduledItem
  switch (item.schedule_type) {
    case 'daily':
      return `Daily at ${item.schedule_time}`
    case 'weekly':
      return `Weekly on ${dayNames[item.schedule_day_of_week || 0]} at ${item.schedule_time}`
    case 'monthly':
      return `Monthly on day ${item.schedule_day_of_month} at ${item.schedule_time}`
    default:
      return 'Unknown'
  }
}

const getSchedulableLabel = (): string => {
  if (props.scheduledItem.apply_to_all) {
    const type = props.scheduledItem.schedulable_type.split('\\').pop()
    if (type === 'Carrier') {
      return 'All Carriers'
    }
    return `All ${type}s`
  }

  if (!props.scheduledItem.schedulable) return 'Unknown'

  const type = props.scheduledItem.schedulable_type.split('\\').pop()
  if (type === 'Carrier') {
    return `${props.scheduledItem.schedulable.short_code} - ${props.scheduledItem.schedulable.name}`
  }

  return props.scheduledItem.schedulable.name
}

const formatDate = (date: string): string => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <Head :title="scheduledItem.name" />

  <AdminLayout>
    <div class="p-6">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            {{ scheduledItem.name }}
          </h1>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Scheduled Email Item
          </p>
        </div>
        <div class="flex gap-3">
          <ActionIconButton
            action="edit"
            :href="route('admin.scheduled-items.edit', scheduledItem.id)"
            title="Edit Scheduled Item"
          />
          <Link
            :href="route('admin.scheduled-items.index')"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          >
            Back
          </Link>
        </div>
      </div>

      <!-- Details Grid -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <!-- Schedule Info -->
          <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Schedule
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Schedule Pattern</p>
                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">
                  {{ getScheduleLabel() }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Type</p>
                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100 capitalize">
                  {{ scheduledItem.schedule_type }}
                </p>
              </div>
            </div>
          </div>

          <!-- Target Info -->
          <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Target
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Target Type</p>
                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">
                  {{ scheduledItem.schedulable_type.split('\\').pop() || 'Unknown' }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Target Model</p>
                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">
                  {{ getSchedulableLabel() }}
                </p>
              </div>
            </div>
          </div>

          <!-- Template Info -->
          <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Template
            </h2>
            <div v-if="scheduledItem.template" class="space-y-4">
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Template Name</p>
                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">
                  {{ scheduledItem.template.name }}
                </p>
              </div>
              <div v-if="scheduledItem.template.subject">
                <p class="text-sm text-gray-600 dark:text-gray-400">Subject</p>
                <p class="mt-1 text-base text-gray-900 dark:text-gray-100">
                  {{ scheduledItem.template.subject }}
                </p>
              </div>
              <div v-if="scheduledItem.template.message">
                <p class="text-sm text-gray-600 dark:text-gray-400">Message</p>
                <div class="mt-1 p-4 bg-gray-50 dark:bg-gray-700 rounded text-sm text-gray-700 dark:text-gray-300 prose dark:prose-invert max-w-none">
                  <!-- Render HTML safely if message contains HTML -->
                  <div v-html="scheduledItem.template.message" />
                </div>
              </div>
            </div>
            <div v-else class="text-gray-500 dark:text-gray-400">
              — No template assigned —
            </div>
          </div>

          <!-- Metadata -->
          <div class="p-6 bg-gray-50 dark:bg-gray-900/50">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Metadata
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
              <div>
                <p class="text-gray-600 dark:text-gray-400">Created</p>
                <p class="mt-1 text-gray-900 dark:text-gray-100">
                  {{ formatDate(scheduledItem.created_at) }}
                </p>
              </div>
              <div>
                <p class="text-gray-600 dark:text-gray-400">Last Updated</p>
                <p class="mt-1 text-gray-900 dark:text-gray-100">
                  {{ formatDate(scheduledItem.updated_at) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
