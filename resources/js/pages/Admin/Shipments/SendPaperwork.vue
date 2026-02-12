<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { ref } from 'vue'

const props = defineProps<{
  shipment: {
    id: number
    shipment_number: string
    bol: string | null
    po_number: string | null
    status: string
    pickup_location: { short_code: string; name: string | null } | null
    dc_location: { short_code: string; name: string | null } | null
    carrier: { name: string; short_code: string } | null
    drop_date: string | null
    pickup_date: string | null
    delivery_date: string | null
    rack_qty: number
    load_bar_qty: number
    strap_qty: number
    trailer: string | null
    drayage: boolean
    on_site: boolean
    shipped: boolean
    recycling_sent: boolean
    paperwork_sent: boolean
    delivery_alert_sent: boolean
    created_at: string
    updated_at: string
  }
  templates: Array<{
    id: number
    name: string
    model_type: string
    model_id: number
    subject: string | null
    message: string | null
    created_at: string
    updated_at: string
  }>
}>()

const form = useForm({
  template_id: null as number | null,
  lrc_file: null as File | null,
  bol_file: null as File | null,
})

const lrcDragActive = ref(false)
const bolDragActive = ref(false)

// Prevent default browser behavior on dragover (required for drop to work)
const preventDefaults = (e) => {
  e.preventDefault()
  e.stopPropagation()
}

// LRC drag & drop handlers
const lrcDragEnter = (e) => {
  preventDefaults(e)
  lrcDragActive.value = true
}

const lrcDragLeave = (e) => {
  preventDefaults(e)
  lrcDragActive.value = false
}

const lrcDrop = (e) => {
  preventDefaults(e)
  lrcDragActive.value = false
  const files = e.dataTransfer?.files
  if (files?.length > 0) {
    form.lrc_file = files[0]
  }
}

const lrcFileSelected = (e) => {
  const files = e.target.files
  if (files?.length > 0) {
    form.lrc_file = files[0]
  }
}

// BOL drag & drop handlers (same logic)
const bolDragEnter = (e) => {
  preventDefaults(e)
  bolDragActive.value = true
}

const bolDragLeave = (e) => {
  preventDefaults(e)
  bolDragActive.value = false
}

const bolDrop = (e) => {
  preventDefaults(e)
  bolDragActive.value = false
  const files = e.dataTransfer?.files
  if (files?.length > 0) {
    form.bol_file = files[0]
  }
}

const bolFileSelected = (e) => {
  const files = e.target.files
  if (files?.length > 0) {
    form.bol_file = files[0]
  }
}

// Submit paperwork
const submit = () => {
  if (!form.template_id) {
    Swal.fire('Error', 'Please select a template.', 'error')
    return
  }

  if (!form.lrc_file && !form.bol_file) {
    Swal.fire('Error', 'Please upload at least one file.', 'error')
    return
  }

  form.post(route('admin.shipments.send-paperwork', props.shipment.id), {
    forceFormData: true, // important for file uploads
    onSuccess: () => {
      Swal.fire('Success', 'Paperwork sent successfully!', 'success')
      form.reset()
    },
    onError: (response) => {
      Swal.fire('Error', 'Failed to send paperwork. Check the form.', 'error')
      console.error('Send paperwork error:', response)
    }
  })
}
</script>

<template>
  <Head title="Send Paperwork" />

  <AdminLayout>
    <div class="max-w-2xl space-y-6 px-4 py-12 sm:px-6 lg:px-8">
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Send Paperwork</h1>
        <p class="mt-2 text-base text-gray-600 dark:text-gray-400">
          Send LRC and BOL documents for {{ shipment.pickup_location?.name || 'Unnamed' }}
        </p>
      </div>

      <!-- No Template Message -->
      <div
        v-if="templates.length === 0"
        class="rounded-md border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-900/20"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
              Template Required
            </h3>
            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
              <p>
                A template must be created for this pickup location before you
                can send paperwork. Please contact your administrator.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Form -->
      <form v-if="templates.length > 0" @submit.prevent="submit" class="space-y-6">
        <!-- Template Selection -->
        <div>
          <label for="template_id" class="block text-sm font-medium text-gray-900 dark:text-white">
            Select Template
          </label>
          <select
            id="template_id"
            v-model="form.template_id"
            required
            class="mt-2 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-blue-400 dark:focus:ring-blue-400 sm:text-sm"
          >
            <option value="">-- Choose a template --</option>
            <option v-for="template in templates" :key="template.id" :value="template.id">
              {{ template.name }}
            </option>
          </select>
          <p v-if="form.errors.template_id" class="mt-1 text-sm text-red-600">{{ form.errors.template_id }}</p>
        </div>

        <!-- LRC File Upload -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-white">
            LRC File (PDF)
          </label>
          <div
            :class="[
              'relative mt-2 rounded-lg border-2 border-dashed p-6 text-center transition-colors cursor-pointer',
              lrcDragActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800',
            ]"
            @dragenter="lrcDragActive = true"
            @dragleave="lrcDragActive = false"
            @dragover.prevent
            @drop="lrcDrop"
            @click="$refs.lrcInput?.click()"
          >
            <input
              ref="lrcInput"
              type="file"
              accept=".pdf"
              class="hidden"
              @change="lrcFileSelected"
            />
            <svg
              v-if="!form.lrc_file"
              class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600"
              stroke="currentColor"
              fill="none"
              viewBox="0 0 48 48"
            >
              <path
                d="M28 8H12a4 4 0 00-4 4v24a4 4 0 004 4h24a4 4 0 004-4V20m-8-12v8m0 0l-4-4m4 4l4-4"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <div v-if="!form.lrc_file" class="mt-2">
              <span class="font-medium text-blue-600 dark:text-blue-400">Click to select</span>
              <span class="text-gray-500 dark:text-gray-400"> or drag and drop</span>
            </div>
            <div v-else class="text-sm text-gray-900 dark:text-white">
              <p class="font-medium">{{ form.lrc_file.name }}</p>
              <p class="text-gray-500 dark:text-gray-400">
                {{ (form.lrc_file.size / 1024 / 1024).toFixed(2) }} MB
              </p>
              <button
                type="button"
                @click="form.lrc_file = null"
                class="mt-2 text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300"
              >
                Remove
              </button>
            </div>
          </div>
          <p v-if="form.errors.lrc_file" class="mt-1 text-sm text-red-600">{{ form.errors.lrc_file }}</p>
        </div>

        <!-- BOL File Upload -->
        <div>
          <label class="block text-sm font-medium text-gray-900 dark:text-white">
            BOL File (PDF)
          </label>
          <div
            :class="[
              'relative mt-2 rounded-lg border-2 border-dashed p-6 text-center transition-colors cursor-pointer',
              bolDragActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800',
            ]"
            @dragenter="bolDragActive = true"
            @dragleave="bolDragActive = false"
            @dragover.prevent
            @drop="bolDrop"
            @click="$refs.bolInput?.click()"
          >
            <input
              ref="bolInput"
              type="file"
              accept=".pdf"
              class="hidden"
              @change="bolFileSelected"
            />
            <svg
              v-if="!form.bol_file"
              class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600"
              stroke="currentColor"
              fill="none"
              viewBox="0 0 48 48"
            >
              <path
                d="M28 8H12a4 4 0 00-4 4v24a4 4 0 004 4h24a4 4 0 004-4V20m-8-12v8m0 0l-4-4m4 4l4-4"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <div v-if="!form.bol_file" class="mt-2">
              <span class="font-medium text-blue-600 dark:text-blue-400">Click to select</span>
              <span class="text-gray-500 dark:text-gray-400"> or drag and drop</span>
            </div>
            <div v-else class="text-sm text-gray-900 dark:text-white">
              <p class="font-medium">{{ form.bol_file.name }}</p>
              <p class="text-gray-500 dark:text-gray-400">
                {{ (form.bol_file.size / 1024 / 1024).toFixed(2) }} MB
              </p>
              <button
                type="button"
                @click="form.bol_file = null"
                class="mt-2 text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300"
              >
                Remove
              </button>
            </div>
          </div>
          <p v-if="form.errors.bol_file" class="mt-1 text-sm text-red-600">{{ form.errors.bol_file }}</p>
        </div>

        <!-- Submit Button -->
        <div class="flex gap-3 pt-4">
          <button
            type="submit"
            :disabled="form.processing || !form.lrc_file && !form.bol_file || !form.template_id"
            class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ form.processing ? 'Sending...' : 'Send Paperwork' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>