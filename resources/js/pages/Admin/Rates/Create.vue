<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue' // adjust path if needed
import InputError from '@/components/InputError.vue' // assuming you have this helper component

const props = defineProps<{
  locations: Array<{ id: number; short_code: string; name: string | null }>
  carriers: Array<{ id: number; name: string; short_code?: string }>
}>()

const form = useForm({
  name: '',
  type: 'per_mile' as 'flat' | 'per_mile',
  rate: null as number | null,
  pickup_location_id: null as number | null,
  dc_location_id: null as number | null,
  carrier_id: null as number | null,
  effective_from: '',
  effective_to: '',
})

const submit = () => {
  form.post(route('admin.rates.store'), {
    onSuccess: () => {
      form.reset()
    },
  })
}

const isPerMile = () => form.type === 'per_mile'
</script>

<template>
  <Head title="Create Rate" />

  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Create New Freight Rate
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Define a rate for a specific lane, carrier, and validity period.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-8 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
        <!-- Name -->
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Rate Name <span class="text-red-500">*</span>
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
            placeholder="e.g. Dallas → Chicago - Dry Van Priority"
            required
          />
          <InputError :message="form.errors.name" class="mt-2" />
        </div>

        <!-- Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Rate Type <span class="text-red-500">*</span>
          </label>
          <div class="mt-2 flex space-x-6">
            <label class="inline-flex items-center">
              <input
                type="radio"
                v-model="form.type"
                value="per_mile"
                class="form-radio text-blue-600"
                required
              />
              <span class="ml-2 text-sm text-gray-900 dark:text-gray-100">Per Mile</span>
            </label>
            <label class="inline-flex items-center">
              <input
                type="radio"
                v-model="form.type"
                value="flat"
                class="form-radio text-blue-600"
              />
              <span class="ml-2 text-sm text-gray-900 dark:text-gray-100">Flat Rate</span>
            </label>
          </div>
          <InputError :message="form.errors.type" class="mt-2" />
        </div>

        <!-- Rate Value -->
        <div>
          <label for="rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Rate Amount <span class="text-red-500">*</span>
          </label>
          <div class="mt-1 relative rounded-md shadow-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
            </div>
            <input
              id="rate"
              v-model.number="form.rate"
              type="number"
              step="0.01"
              min="0.01"
              class="block w-full pl-7 pr-12 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
              placeholder="e.g. 2.45"
              required
            />
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
              <span class="text-gray-500 dark:text-gray-400 sm:text-sm" v-if="isPerMile()">/ mile</span>
              <span class="text-gray-500 dark:text-gray-400 sm:text-sm" v-else>flat</span>
            </div>
          </div>
          <InputError :message="form.errors.rate" class="mt-2" />
        </div>

        <!-- Pickup Location -->
        <div>
          <label for="pickup_location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Pickup Location
          </label>
          <select
            id="pickup_location_id"
            v-model="form.pickup_location_id"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
          >
            <option :value="null">— Select Pickup Location —</option>
            <option v-for="loc in props.locations" :key="loc.id" :value="loc.id">
              {{ loc.short_code }} — {{ loc.name || 'Unnamed' }}
            </option>
          </select>
          <InputError :message="form.errors.pickup_location_id" class="mt-2" />
        </div>

        <!-- DC Location -->
        <div>
          <label for="dc_location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Distribution Center (DC)
          </label>
          <select
            id="dc_location_id"
            v-model="form.dc_location_id"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
          >
            <option :value="null">— Select DC Location —</option>
            <option v-for="loc in props.locations" :key="loc.id" :value="loc.id">
              {{ loc.short_code }} — {{ loc.name || 'Unnamed' }}
            </option>
          </select>
          <InputError :message="form.errors.dc_location_id" class="mt-2" />
        </div>

        <!-- Carrier -->
        <div>
          <label for="carrier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Carrier
          </label>
          <select
            id="carrier_id"
            v-model="form.carrier_id"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
          >
            <option :value="null">— Select Carrier (optional) —</option>
            <option v-for="carrier in props.carriers" :key="carrier.id" :value="carrier.id">
              {{ carrier.name }} {{ carrier.short_code ? `(${carrier.short_code})` : '' }}
            </option>
          </select>
          <InputError :message="form.errors.carrier_id" class="mt-2" />
        </div>

        <!-- Effective Dates -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="effective_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Effective From
            </label>
            <input
              id="effective_from"
              v-model="form.effective_from"
              type="date"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
            />
            <InputError :message="form.errors.effective_from" class="mt-2" />
          </div>

          <div>
            <label for="effective_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Effective To
            </label>
            <input
              id="effective_to"
              v-model="form.effective_to"
              type="date"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
            />
            <InputError :message="form.errors.effective_to" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Leave blank if no expiration
            </p>
          </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center justify-end space-x-4 pt-6 border-t dark:border-gray-700">
          <Link
            :href="route('admin.rates.index')"
            class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
          >
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
          >
            {{ form.processing ? 'Creating...' : 'Create Rate' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>