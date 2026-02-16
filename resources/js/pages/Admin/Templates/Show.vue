<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed, onMounted } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'
import { Notify } from 'notiflix'

const props = defineProps<{
  template: {
    id: number
    name: string
    model_type: string          // e.g. 'App\\Models\\Carrier' or 'App\\Models\\Location'
    model_id: number
    subject: string | null
    message: string | null
    created_at: string
    updated_at: string
    model: {
      id: number
      name?: string
      short_code?: string
    } | null
  }
}>()

// Helper to display friendly model type
const modelTypeDisplay = computed(() => {
  const type = props.template.model_type.split('\\').pop() || 'Unknown'
  return type
})

const page = usePage()

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
  <Head title="View Template" />

  <AdminLayout>
    <div class="p-6 max-w-4xl">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Template: {{ template.name }}
        </h1>
        <div class="space-x-4">
          <a
            :href="route('admin.templates.edit', template.id)"
            class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
          >
            Edit Template
          </a>
          <a
            :href="route('admin.templates.index')"
            class="px-5 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
          >
            Back to List
          </a>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 space-y-6">
          <!-- Basic Info -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
              <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ template.name }}
              </dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Related To</dt>
              <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                <span v-if="template.model">
                  {{ modelTypeDisplay }}:
                  {{ template.model.short_code || template.model.name || 'ID ' + template.model.id }}
                </span>
                <span v-else>—</span>
              </dd>
            </div>
          </div>

          <!-- Subject -->
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Subject</dt>
            <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
              {{ template.subject || '—' }}
            </dd>
          </div>

          <!-- Message Content -->
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Message Content</dt>
            <dd class="mt-1">
              <div class="prose dark:prose-invert max-w-none p-4 bg-gray-50 dark:bg-gray-900/50 rounded border border-gray-200 dark:border-gray-700 whitespace-pre-wrap font-mono text-gray-900 dark:text-gray-100">
                <div v-if="template.message" v-html="template.message"></div>
                <div v-else class="text-gray-500 dark:text-gray-400 italic">
                  No message content
                </div>
              </div>
            </dd>
          </div>

          <!-- Timestamps -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
              <dd class="mt-1 text-gray-900 dark:text-gray-100">
                {{ new Date(template.created_at).toLocaleString() }}
              </dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</dt>
              <dd class="mt-1 text-gray-900 dark:text-gray-100">
                {{ new Date(template.updated_at).toLocaleString() }}
              </dd>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
