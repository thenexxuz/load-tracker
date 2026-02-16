<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { Notify } from 'notiflix';

const props = defineProps<{
  rate: {
    id: number
    carrier_id: number
    pickup_location_id: number
    dc_location_id: number
    rate: number
  }
  carriers: Array<{ id: number; name: string; short_code: string }>
  pickupLocations: Array<{ id: number; short_code: string; name: string | null }>
  dcLocations: Array<{ id: number; short_code: string; name: string | null }>
}>()

const form = useForm({
  carrier_id: props.rate.carrier_id,
  pickup_location_id: props.rate.pickup_location_id,
  dc_location_id: props.rate.dc_location_id,
  rate: props.rate.rate,
})

const submit = () => {
  form.put(route('admin.rates.update', props.rate.id))
}
</script>

<template>
  <Head title="Edit Rate" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Edit Rate
      </h1>

      <!-- Error banner -->
      <div v-if="Object.keys(form.errors).length" class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg">
        Please fix the errors below.
      </div>

      <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 max-w-2xl">
        <div class="grid grid-cols-1 gap-6">
          <!-- Carrier -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Carrier <span class="text-red-600 dark:text-red-400">*</span>
            </label>
            <select
              v-model="form.carrier_id"
              required
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            >
              <option :value="null" disabled>Select Carrier</option>
              <option v-for="carrier in carriers" :key="carrier.id" :value="carrier.id">
                {{ carrier.short_code }} - {{ carrier.name }}
              </option>
            </select>
            <p v-if="form.errors.carrier_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.carrier_id }}
            </p>
          </div>

          <!-- Pickup Location -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Pickup Location <span class="text-red-600 dark:text-red-400">*</span>
            </label>
            <select
              v-model="form.pickup_location_id"
              required
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            >
              <option :value="null" disabled>Select Pickup Location</option>
              <option v-for="loc in pickupLocations" :key="loc.id" :value="loc.id">
                {{ loc.short_code }} - {{ loc.name || 'Unnamed' }}
              </option>
            </select>
            <p v-if="form.errors.pickup_location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.pickup_location_id }}
            </p>
          </div>

          <!-- DC Location -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              DC Location <span class="text-red-600 dark:text-red-400">*</span>
            </label>
            <select
              v-model="form.dc_location_id"
              required
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            >
              <option :value="null" disabled>Select DC Location</option>
              <option v-for="loc in dcLocations" :key="loc.id" :value="loc.id">
                {{ loc.short_code }} - {{ loc.name || 'Unnamed' }}
              </option>
            </select>
            <p v-if="form.errors.dc_location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.dc_location_id }}
            </p>
          </div>

          <!-- Rate -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Rate <span class="text-red-600 dark:text-red-400">*</span>
            </label>
            <input
              v-model="form.rate"
              type="number"
              step="0.01"
              min="0"
              required
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.rate" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.rate }}
            </p>
          </div>
        </div>

        <div class="flex justify-end space-x-4 mt-8">
          <a href="javascript:history.back()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
            Cancel
          </a>
          <button
            type="submit"
            :disabled="form.processing"
            class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-md font-medium transition-colors disabled:opacity-50"
          >
            {{ form.processing ? 'Updating...' : 'Update Rate' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
