<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import Editor from '@tinymce/tinymce-vue'

const props = defineProps<{
  carriers: Array<{ id: number; name: string; short_code: string }>
  locations: Array<{ id: number; short_code: string; name: string | null }>
}>()

const form = useForm({
  name: '',
  model_type: '', // 'carrier' or 'location'
  model_id: null as number | null,
  subject: '',
  message: '<p>Start typing your message here...</p>',
})

// TinyMCE configuration
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
  branding: false, // hide TinyMCE branding
}

// Watch model_type change → reset model_id
watch(() => form.model_type, (newType, oldType) => {
  if (newType !== oldType) {
    form.model_id = null
  }
})

const submit = () => {
  // TinyMCE content is already in form.message via v-model
  const payload = {
    ...form.data(),
    model_type: form.model_type === 'carrier' ? 'App\\Models\\Carrier' : 'App\\Models\\Location',
  }

  form.post(route('admin.templates.store'), {
    data: payload,
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Created!',
        text: 'Template created successfully.',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
      })
      form.reset()
      form.message = '<p>Start typing your message here...</p>'
    },
    onError: () => {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Please fix the errors in the form.'
      })
    }
  })
}
</script>

<template>
  <Head title="Create Template" />

  <AdminLayout>
    <div class="p-6 max-w-4xl">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Create New Template
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
            </select>
            <p v-if="form.errors.model_type" class="mt-1 text-sm text-red-600">{{ form.errors.model_type }}</p>
          </div>

          <div>
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
        <div>
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
            api-key="no-api-key"  <!-- leave blank for self-hosted or use your key -->
            tag-name="textarea"
          ></Editor>

          <p v-if="form.errors.message" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ form.errors.message }}
          </p>
        </div>

        <div class="flex justify-end gap-4 mt-6">
          <a href="javascript:history.back()" class="px-6 py-2 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600">
            Cancel
          </a>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            Create Template
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
