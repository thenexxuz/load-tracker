<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { route } from 'ziggy-js'

import InputError from '@/components/InputError.vue'
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
    apply_to_all: boolean
    schedulable_type: string
    schedulable_id: string | null
  }
  carriers: Array<{
    id: string
    name: string
    short_code: string
  }>
  templates: Array<{
    id: number
    name: string
    model_type: string
  }>
}>()

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

const form = useForm({
  name: props.scheduledItem.name,
  schedule_type: props.scheduledItem.schedule_type as 'daily' | 'weekly' | 'monthly',
  schedule_time: props.scheduledItem.schedule_time,
  schedule_day_of_week: props.scheduledItem.schedule_day_of_week,
  schedule_day_of_month: props.scheduledItem.schedule_day_of_month,
  template_id: props.scheduledItem.template_id,
  apply_to_all: props.scheduledItem.apply_to_all,
  schedulable_type: props.scheduledItem.schedulable_type.includes('Carrier') ? 'carrier' : 'carrier',
  schedulable_id: props.scheduledItem.schedulable_id,
})

const submit = () => {
  form.put(route('admin.scheduled-items.update', props.scheduledItem.id), {
    onSuccess: () => {
      // Success handling is in the controller
    },
  })
}

const showDayOfWeek = computed(() => form.schedule_type === 'weekly')
const showDayOfMonth = computed(() => form.schedule_type === 'monthly')

// Reset conditional fields when schedule_type changes
const updateScheduleType = (type: 'daily' | 'weekly' | 'monthly') => {
  form.schedule_type = type
  form.schedule_day_of_week = null
  form.schedule_day_of_month = null
}
</script>

<template>
  <Head title="Edit Scheduled Item" />

  <AdminLayout>
    <div class="p-6">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Edit Scheduled Email Item
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Update the schedule and configuration for this automatic email.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-8 bg-white dark:bg-gray-800 p-8 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700">
        <!-- Item Name -->
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Item Name <span class="text-red-500">*</span>
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            required
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
            placeholder="e.g. Weekly Status Update"
          />
          <InputError :message="form.errors.name" class="mt-1.5 text-sm" />
        </div>

        <!-- Schedule Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Schedule Type <span class="text-red-500">*</span>
          </label>
          <div class="grid grid-cols-3 gap-4">
            <label class="flex items-center p-4 border rounded-lg cursor-pointer" :class="form.schedule_type === 'daily' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600'">
              <input
                type="radio"
                @change="updateScheduleType('daily')"
                :checked="form.schedule_type === 'daily'"
                class="form-radio h-5 w-5 text-blue-600"
              />
              <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">Daily</span>
            </label>

            <label class="flex items-center p-4 border rounded-lg cursor-pointer" :class="form.schedule_type === 'weekly' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600'">
              <input
                type="radio"
                @change="updateScheduleType('weekly')"
                :checked="form.schedule_type === 'weekly'"
                class="form-radio h-5 w-5 text-blue-600"
              />
              <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">Weekly</span>
            </label>

            <label class="flex items-center p-4 border rounded-lg cursor-pointer" :class="form.schedule_type === 'monthly' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600'">
              <input
                type="radio"
                @change="updateScheduleType('monthly')"
                :checked="form.schedule_type === 'monthly'"
                class="form-radio h-5 w-5 text-blue-600"
              />
              <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">Monthly</span>
            </label>
          </div>
          <InputError :message="form.errors.schedule_type" class="mt-1.5 text-sm" />
        </div>

        <!-- Schedule Time (All types) -->
        <div>
          <label for="schedule_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Time of Day (CST) <span class="text-red-500">*</span>
          </label>
          <input
            id="schedule_time"
            v-model="form.schedule_time"
            type="time"
            required
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Scheduled emails are evaluated in Central Time.
          </p>
          <InputError :message="form.errors.schedule_time" class="mt-1.5 text-sm" />
        </div>

        <!-- Day of Week (Weekly only) -->
        <div v-if="showDayOfWeek">
          <label for="schedule_day_of_week" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Day of Week <span class="text-red-500">*</span>
          </label>
          <select
            id="schedule_day_of_week"
            v-model.number="form.schedule_day_of_week"
            required
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
          >
            <option :value="null" disabled>Select a day</option>
            <option v-for="(day, index) in dayNames" :key="index" :value="index">
              {{ day }}
            </option>
          </select>
          <InputError :message="form.errors.schedule_day_of_week" class="mt-1.5 text-sm" />
        </div>

        <!-- Day of Month (Monthly only) -->
        <div v-if="showDayOfMonth">
          <label for="schedule_day_of_month" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Day of Month <span class="text-red-500">*</span>
          </label>
          <input
            id="schedule_day_of_month"
            v-model.number="form.schedule_day_of_month"
            type="number"
            min="1"
            max="31"
            required
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
            placeholder="1-31"
          />
          <InputError :message="form.errors.schedule_day_of_month" class="mt-1.5 text-sm" />
        </div>

        <!-- Target Model Type -->
        <div>
          <label for="schedulable_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Target Type <span class="text-red-500">*</span>
          </label>
          <select
            id="schedulable_type"
            v-model="form.schedulable_type"
            required
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
          >
            <option value="carrier">Carrier</option>
          </select>
          <InputError :message="form.errors.schedulable_type" class="mt-1.5 text-sm" />
        </div>

        <!-- Apply to All Toggle -->
        <div class="flex items-center">
          <input
            id="apply_to_all"
            v-model="form.apply_to_all"
            type="checkbox"
            class="h-5 w-5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          />
          <label for="apply_to_all" class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
            Apply to all {{ form.schedulable_type === 'carrier' ? 'carriers' : 'targets' }}
          </label>
        </div>

        <!-- Target Model -->
        <div v-if="!form.apply_to_all">
          <label for="schedulable_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Target {{ form.schedulable_type === 'carrier' ? 'Carrier' : 'Model' }} <span class="text-red-500">*</span>
          </label>
          <select
            id="schedulable_id"
            v-model="form.schedulable_id"
            required
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
          >
            <option :value="null" disabled>
              Select target
            </option>
            <option v-for="carrier in props.carriers" :key="carrier.id" :value="carrier.id">
              {{ carrier.short_code }} - {{ carrier.name }}
            </option>
          </select>
          <InputError :message="form.errors.schedulable_id" class="mt-1.5 text-sm" />
        </div>

        <!-- Template (Optional) -->
        <div>
          <label for="template_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Email Template (Optional)
          </label>
          <select
            id="template_id"
            v-model.number="form.template_id"
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
          >
            <option :value="null">— None —</option>
            <option v-for="template in props.templates" :key="template.id" :value="template.id">
              {{ template.name }}
            </option>
          </select>
          <InputError :message="form.errors.template_id" class="mt-1.5 text-sm" />
        </div>

        <!-- Submit Buttons -->
        <div class="flex gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </button>
          <a
            :href="route('admin.scheduled-items.index')"
            class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          >
            Cancel
          </a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
