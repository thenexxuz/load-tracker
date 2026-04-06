<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import Editor from '@tinymce/tinymce-vue'
import { watch } from 'vue'
import { route } from 'ziggy-js'

import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  template: {
    id: number
    name: string
    model_type: string  // e.g. 'App\\Models\\Carrier' or 'App\\Models\\Location'
    model_id: number | null
    subject: string | null
    message: string | null
  }
  carriers: Array<{ id: number; name: string; short_code: string }>
  locations: Array<{ id: number; short_code: string; name: string | null }>
}>()

const form = useForm({
  name: props.template.name,
  model_type: props.template.model_type === 'App\\Models\\Carrier'
    ? 'carrier'
    : props.template.model_type === 'App\\Models\\Location'
      ? 'location'
      : props.template.model_type === 'App\\Models\\Template'
        ? 'template_token'
        : 'scheduled_item',
  model_id: props.template.model_id as number | string | null,
  subject: props.template.subject || '',
  message: props.template.message || '<p>Start typing your message here...</p>',
})

// TinyMCE configuration (same as Create)
const tinyMceInit = {
  height: 400,
  menubar: false,
  plugins: [
    'advlist','autolink','lists','link','image','charmap','preview','anchor',
    'searchreplace','visualblocks','code','fullscreen',
    'insertdatetime','media','table','help','wordcount'
  ],
  toolbar: 'undo redo | formatselect | ' +
    'bold italic underline forecolor backcolor | alignleft aligncenter ' +
    'alignright alignjustify | table bullist numlist outdent indent | ' +
    'removeformat | help | link image',
  content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
  placeholder: 'Type your message here...',
  branding: false,
}

// Reset model_id when model_type changes
watch(() => form.model_type, (newType, oldType) => {
  if (newType !== oldType) {
    form.model_id = null

    if (newType === 'template_token') {
      form.subject = ''
    }
  }
})

const submit = () => {
  form.transform((data) => ({
    ...data,
    model_id: ['scheduled_item', 'template_token'].includes(data.model_type)
      ? null
      : (data.model_id === null || data.model_id === '' ? null : data.model_id),
    subject: data.model_type === 'template_token' ? null : data.subject,
  })).put(route('admin.templates.update', props.template.id), {
    onError: (response) => {
      console.error('Update error:', response)
    }
  })
}
</script>

<template>
  <Head title="Edit Template" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Edit Template: {{ template.name }}
      </h1>

      <div v-if="Object.keys(form.errors).length" class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg">
        Please fix the errors below.
      </div>

      <form @submit.prevent="submit" class="space-y-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow border dark:border-gray-700">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium mb-1">Template Name <span class="text-red-600">*</span></label>
          <input
            v-model="form.name"
            type="text"
            required
            class="w-full border rounded p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
            placeholder="e.g. Late Shipment Notification"
          />
          <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
        </div>

        <!-- Model Type + ID -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium mb-1">Related Model Type <span class="text-red-600">*</span></label>
            <select
              v-model="form.model_type"
              required
              class="w-full border rounded p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
            >
              <option value="" disabled>Select type</option>
              <option value="carrier">Carrier</option>
              <option value="location">Location</option>
              <option value="scheduled_item">Scheduled Item</option>
              <option value="template_token">Template Token</option>
            </select>
            <p v-if="form.errors.model_type" class="mt-1 text-sm text-red-600">{{ form.errors.model_type }}</p>
          </div>

          <div v-if="!['scheduled_item', 'template_token'].includes(form.model_type)">
            <label class="block text-sm font-medium mb-1">Related Model <span class="text-red-600">*</span></label>
            <select
              v-model="form.model_id"
              :disabled="!form.model_type"
              required
              class="w-full border rounded p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
            >
              <option value="" disabled>
                {{ form.model_type ? 'Select ' + (form.model_type === 'carrier' ? 'Carrier' : 'Location') : 'Select type first' }}
              </option>

              <template v-if="form.model_type === 'carrier'">
                <option v-for="c in carriers" :key="c.id" :value="c.id">
                  {{ c.short_code }} - {{ c.name }}
                </option>
              </template>

              <template v-if="form.model_type === 'location'">
                <option v-for="l in locations" :key="l.id" :value="l.id">
                  {{ l.short_code }} - {{ l.name || 'Unnamed' }}
                </option>
              </template>

              <option v-if="form.model_type && ((form.model_type === 'carrier' && !carriers.length) || (form.model_type === 'location' && !locations.length))" disabled>
                No {{ form.model_type }}s available
              </option>
            </select>
            <p v-if="form.errors.model_id" class="mt-1 text-sm text-red-600">{{ form.errors.model_id }}</p>
          </div>
        </div>

        <!-- Subject -->
        <div v-if="form.model_type !== 'template_token'">
          <label class="block text-sm font-medium mb-1">Subject</label>
          <input
            v-model="form.subject"
            type="text"
            class="w-full border rounded p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
            placeholder="e.g. Shipment Update Notification"
          />
          <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
        </div>

        <!-- TinyMCE Message Field -->
        <div>
          <label class="block text-sm font-medium mb-2">Message</label>

          <Editor
            v-model="form.message"
            :init="tinyMceInit"
            api-key="no-api-key"  <!-- leave blank for self-hosted -->
            tag-name="textarea"
          ></Editor>

          <p v-if="form.errors.message" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ form.errors.message }}
          </p>
        </div>

        <div class="flex justify-end gap-4 mt-6">
          <a :href="route('admin.templates.index')" class="px-6 py-2 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600">
            Cancel
          </a>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
          >
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
