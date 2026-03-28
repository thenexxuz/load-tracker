<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import InputError from '@/components/InputError.vue'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  locations: Array<{
    id: number
    short_code: string
    name: string | null
    city: string | null
    state: string | null
  }>
  carriers: Array<{
    id: number
    name: string
    short_code?: string | null
  }>
}>()

const form = useForm({
  name: '',
  type: 'per_mile' as 'flat' | 'per_mile',
  rate: null as number | null,
  pickup_location_id: null as number | null,
  destination_city: '',
  destination_state: '',
  destination_country: '',
  carrier_id: null as number | null,
  effective_from: '',
  effective_to: '',
})

const submit = () => {
  form.post(route('admin.rates.store'), {
    onSuccess: () => {
      form.reset()
      // Optional: redirect or show success message
    },
  })
}

const isPerMile = computed(() => form.type === 'per_mile')

const rateLabel = computed(() => {
  return isPerMile.value ? '/ mile' : 'flat'
})
</script>

<template>
  <Head title="Create New Rate" />

  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Create Freight Rate
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Define pricing for a specific lane and carrier.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-8 bg-white dark:bg-gray-800 p-8 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700">
        <!-- Rate Name -->
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Rate Name <span class="text-red-500">*</span>
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            required
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
            placeholder="e.g. Dallas → Chicago Dry Van - Priority 2026"
          />
          <InputError :message="form.errors.name" class="mt-1.5 text-sm" />
        </div>

        <!-- Rate Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Rate Type <span class="text-red-500">*</span>
          </label>
          <div class="flex space-x-8">
            <label class="inline-flex items-center cursor-pointer">
              <input
                type="radio"
                v-model="form.type"
                value="per_mile"
                class="form-radio h-5 w-5 text-blue-600 border-gray-300 focus:ring-blue-500"
                required
              />
              <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">Per Mile</span>
            </label>
            <label class="inline-flex items-center cursor-pointer">
              <input
                type="radio"
                v-model="form.type"
                value="flat"
                class="form-radio h-5 w-5 text-blue-600 border-gray-300 focus:ring-blue-500"
              />
              <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">Flat Rate</span>
            </label>
          </div>
          <InputError :message="form.errors.type" class="mt-1.5 text-sm" />
        </div>

        <!-- Rate Amount -->
        <div>
          <label for="rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Rate Amount <span class="text-red-500">*</span>
          </label>
          <div class="relative rounded-lg shadow-sm">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
            </div>
            <input
              id="rate"
              v-model.number="form.rate"
              type="number"
              step="0.01"
              min="0.01"
              required
              class="block w-full pl-10 pr-16 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2.5"
              placeholder="2.45"
            />
            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
              <span class="text-gray-500 dark:text-gray-400 sm:text-sm font-medium">
                {{ rateLabel }}
              </span>
            </div>
          </div>
          <InputError :message="form.errors.rate" class="mt-1.5 text-sm" />
        </div>

        <!-- Locations -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Pickup Location -->
          <div>
            <label for="pickup_location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Pickup Location
            </label>
            <select
              id="pickup_location_id"
              v-model="form.pickup_location_id"
              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2.5 px-3"
            >
              <option :value="null">— Select Pickup —</option>
              <option v-for="loc in props.locations" :key="loc.id" :value="loc.id">
                {{ loc.short_code }} — {{ loc.name || 'Unnamed' }} ({{ loc.city }}, {{ loc.state }})
              </option>
            </select>
            <InputError :message="form.errors.pickup_location_id" class="mt-1.5 text-sm" />
          </div>

        <!-- Destination -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Destination City -->
          <div>
            <label for="destination_city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Destination City
            </label>
            <input
              id="destination_city"
              v-model="form.destination_city"
              type="text"
              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5"
              placeholder="e.g. Chicago"
            />
            <InputError :message="form.errors.destination_city" class="mt-1.5 text-sm" />
          </div>

          <!-- Destination State -->
          <div>
            <label for="destination_state" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Destination State
            </label>
            <input
              id="destination_state"
              v-model="form.destination_state"
              type="text"
              maxlength="2"
              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5 uppercase"
              placeholder="e.g. IL"
            />
            <InputError :message="form.errors.destination_state" class="mt-1.5 text-sm" />
          </div>

          <!-- Destination Country -->
          <div>
            <label for="destination_country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Destination Country
            </label>
            <input
              id="destination_country"
              v-model="form.destination_country"
              type="text"
              maxlength="2"
              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-4 py-2.5 uppercase"
              placeholder="e.g. US"
            />
            <InputError :message="form.errors.destination_country" class="mt-1.5 text-sm" />
          </div>
        </div>
        </div>

        <!-- Carrier -->
        <div>
          <label for="carrier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Carrier (optional)
          </label>
          <select
            id="carrier_id"
            v-model="form.carrier_id"
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2.5 px-3"
          >
            <option :value="null">— Any / Not specified —</option>
            <option v-for="carrier in props.carriers" :key="carrier.id" :value="carrier.id">
              {{ carrier.name }}
              <span v-if="carrier.short_code"> ({{ carrier.short_code }})</span>
            </option>
          </select>
          <InputError :message="form.errors.carrier_id" class="mt-1.5 text-sm" />
        </div>

        <!-- Effective Dates -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="effective_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Effective From
            </label>
            <input
              id="effective_from"
              v-model="form.effective_from"
              type="date"
              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2.5 px-3"
            />
            <InputError :message="form.errors.effective_from" class="mt-1.5 text-sm" />
          </div>

          <div>
            <label for="effective_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Effective To
            </label>
            <input
              id="effective_to"
              v-model="form.effective_to"
              type="date"
              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2.5 px-3"
            />
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
              Leave blank for no expiration date
            </p>
            <InputError :message="form.errors.effective_to" class="mt-1.5 text-sm" />
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-4 pt-6 border-t dark:border-gray-700">
          <Link
            :href="route('admin.rates.index')"
            class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 text-sm font-medium"
          >
            Cancel
          </Link>

          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-medium rounded-lg shadow transition-colors"
          >
            <span v-if="form.processing">Creating...</span>
            <span v-else>Create Rate</span>
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
