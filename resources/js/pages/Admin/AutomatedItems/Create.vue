<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { route } from 'ziggy-js'

import InputError from '@/components/InputError.vue'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  monitorableOptions: Array<{ key: 'shipment' | 'location' | 'carrier'; label: string }>
  monitorableFields: Record<string, string[]>
  roles: Array<{ name: string }>
}>()

const form = useForm({
  name: '',
  monitorable_type: 'shipment' as 'shipment' | 'location' | 'carrier',
  monitored_fields: [] as string[],
  role_name: '',
  is_active: true,
})

const availableFields = computed(() => props.monitorableFields[form.monitorable_type] ?? [])

const monitoredFieldsError = computed(() => {
  const errors = form.errors as Record<string, string>

  if (form.errors.monitored_fields) {
    return form.errors.monitored_fields
  }

  const nestedErrorKey = Object.keys(errors).find((key) => key.startsWith('monitored_fields.'))

  return nestedErrorKey ? errors[nestedErrorKey] : undefined
})

const setMonitorableType = (type: 'shipment' | 'location' | 'carrier') => {
  form.monitorable_type = type
  form.monitored_fields = []
}

const selectAllFields = () => {
  form.monitored_fields = [...availableFields.value]
}

const clearFields = () => {
  form.monitored_fields = []
}

const submit = () => {
  form.post(route('admin.automated-items.store'), {
    onSuccess: () => {
      form.reset()
      form.is_active = true
      form.monitorable_type = 'shipment'
    },
  })
}
</script>

<template>
  <Head title="Create Automated Item" />

  <AdminLayout>
    <div class="mx-auto max-w-4xl p-6">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Create Automated Item
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Configure model changes that should trigger role-based notifications.
        </p>
      </div>

      <form
        class="space-y-8 rounded-xl border border-gray-200 bg-white p-8 shadow-xl dark:border-gray-700 dark:bg-gray-800"
        @submit.prevent="submit"
      >
        <div>
          <label for="name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Item Name <span class="text-red-500">*</span>
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            required
            class="block w-full rounded-lg border-gray-300 px-4 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700"
            placeholder="e.g. Notify when shipment status changes"
          />
          <InputError :message="form.errors.name" class="mt-1.5 text-sm" />
        </div>

        <div>
          <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Model to Monitor <span class="text-red-500">*</span>
          </label>
          <div class="grid grid-cols-3 gap-4">
            <label
              v-for="option in props.monitorableOptions"
              :key="option.key"
              class="cursor-pointer rounded-lg border p-4"
              :class="form.monitorable_type === option.key
                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                : 'border-gray-300 dark:border-gray-600'"
            >
              <input
                type="radio"
                class="form-radio h-5 w-5 text-blue-600"
                :checked="form.monitorable_type === option.key"
                @change="setMonitorableType(option.key)"
              />
              <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ option.label }}</span>
            </label>
          </div>
          <InputError :message="form.errors.monitorable_type" class="mt-1.5 text-sm" />
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Properties to Monitor <span class="text-red-500">*</span>
            </label>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                @click="selectAllFields"
              >
                Select all
              </button>
              <button
                type="button"
                class="text-xs text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                @click="clearFields"
              >
                Clear
              </button>
            </div>
          </div>

          <div class="max-h-56 overflow-y-auto rounded-lg border border-gray-300 p-3 dark:border-gray-600">
            <label
              v-for="field in availableFields"
              :key="field"
              class="flex items-start gap-2 py-1.5 text-sm text-gray-800 dark:text-gray-200"
            >
              <input
                v-model="form.monitored_fields"
                type="checkbox"
                :value="field"
                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span>{{ field }}</span>
            </label>
          </div>
          <InputError :message="monitoredFieldsError" class="mt-1.5 text-sm" />
        </div>

        <div>
          <label for="role_name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Role to Notify <span class="text-red-500">*</span>
          </label>
          <select
            id="role_name"
            v-model="form.role_name"
            required
            class="block w-full rounded-lg border-gray-300 px-4 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700"
          >
            <option value="" disabled>Select a role</option>
            <option v-for="role in props.roles" :key="role.name" :value="role.name">
              {{ role.name }}
            </option>
          </select>
          <InputError :message="form.errors.role_name" class="mt-1.5 text-sm" />
        </div>

        <div class="flex items-center">
          <input
            id="is_active"
            v-model="form.is_active"
            type="checkbox"
            class="h-5 w-5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          />
          <label for="is_active" class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
            Active immediately
          </label>
        </div>

        <div class="flex gap-4 border-t border-gray-200 pt-6 dark:border-gray-700">
          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-blue-600 px-6 py-2 text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {{ form.processing ? 'Creating...' : 'Create Item' }}
          </button>
          <a
            :href="route('admin.automated-items.index')"
            class="rounded-lg border border-gray-300 px-6 py-2 text-gray-900 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
          >
            Cancel
          </a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
