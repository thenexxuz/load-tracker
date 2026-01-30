<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  rate: {
    id: number
    carrier: { name: string; short_code: string } | null
    pickup_location: { short_code: string; name: string | null } | null
    dc_location: { short_code: string; name: string | null } | null
    rate: number
  }
}>()
</script>

<template>
  <Head title="View Rate" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Rate Details
      </h1>

      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 max-w-2xl">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <dt class="font-medium text-gray-700 dark:text-gray-300">Carrier</dt>
          <dd class="text-gray-600 dark:text-gray-400">{{ rate.carrier?.name || '—' }}</dd>

          <dt class="font-medium text-gray-700 dark:text-gray-300">Pickup Location</dt>
          <dd class="text-gray-600 dark:text-gray-400">{{ rate.pickup_location?.short_code || '—' }}</dd>

          <dt class="font-medium text-gray-700 dark:text-gray-300">DC Location</dt>
          <dd class="text-gray-600 dark:text-gray-400">{{ rate.dc_location?.short_code || '—' }}</dd>

          <dt class="font-medium text-gray-700 dark:text-gray-300">Rate</dt>
          <dd class="text-gray-600 dark:text-gray-400">${{ rate.rate.toFixed(2) }}</dd>
        </dl>

        <div class="flex justify-end mt-8">
          <a :href="route('admin.rates.edit', rate.id)" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-md font-medium transition-colors">
            Edit
          </a>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
