<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { format } from 'date-fns'
import { computed } from 'vue'

const props = defineProps<{
  rate: {
    id: number
    name: string | null
    type: 'flat' | 'per_mile'
    rate: number
    pickup_location?: {
      short_code: string
      name: string | null
    } | null
    dc_location?: {
      short_code: string
      name: string | null
    } | null
    carrier?: {
      name: string
      short_code: string | null
    } | null
    effective_from: string | null
    effective_to: string | null
    created_at: string
    updated_at: string
  }
}>()

const { rate } = props

const formatDate = (date: string | null): string => {
  if (!date) return '—'
  try {
    return format(new Date(date), 'MMM d, yyyy')
  } catch {
    return 'Invalid date'
  }
}

const isActive = (): boolean => {
  const now = new Date('2026-03-11') // current date as per context
  const start = rate.effective_from ? new Date(rate.effective_from) : null
  const end = rate.effective_to ? new Date(rate.effective_to) : null

  if (start && start > now) return false
  if (end && end < now) return false
  return true
}

const rateDisplay = computed(() => {
  if (rate.type === 'flat') {
    return `$${rate.rate.toFixed(2)} flat`
  }
  return `$${rate.rate.toFixed(2)} / mile`
})
</script>

<template>
  <Head title="Rate Details" />

  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Rate: {{ rate.name || 'Unnamed Rate' }}
        </h1>
        <div class="space-x-4">
          <Link
            :href="route('admin.rates.edit', rate.id)"
            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium"
          >
            Edit Rate
          </Link>
          <Link
            :href="route('admin.rates.index')"
            class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
          >
            Back to List
          </Link>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-8">
          <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
            <!-- Name -->
            <div class="col-span-full">
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Rate Name</dt>
              <dd class="mt-1.5 text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ rate.name || '—' }}
              </dd>
            </div>

            <!-- Type & Rate -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100 capitalize font-medium">
                {{ rate.type }}
              </dd>
            </div>

            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Rate Amount</dt>
              <dd class="mt-1.5 text-xl font-bold text-green-700 dark:text-green-400">
                {{ rateDisplay }}
              </dd>
            </div>

            <!-- Lane -->
            <div class="col-span-full">
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Lane (Pickup → DC)</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100">
                <div class="flex items-center gap-3">
                  <span class="font-medium">
                    {{ rate.pickup_location?.short_code || '—' }}
                    <span v-if="rate.pickup_location?.name" class="text-gray-600 dark:text-gray-400 ml-1">
                      ({{ rate.pickup_location.name }})
                    </span>
                  </span>
                  <span class="text-gray-500 dark:text-gray-400">→</span>
                  <span class="font-medium">
                    {{ rate.dc_location?.short_code || '—' }}
                    <span v-if="rate.dc_location?.name" class="text-gray-600 dark:text-gray-400 ml-1">
                      ({{ rate.dc_location.name }})
                    </span>
                  </span>
                </div>
              </dd>
            </div>

            <!-- Carrier -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Carrier</dt>
              <dd class="mt-1.5 text-gray-900 dark:text-gray-100">
                <span v-if="rate.carrier">
                  {{ rate.carrier.name }}
                  <span v-if="rate.carrier.short_code" class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                    ({{ rate.carrier.short_code }})
                  </span>
                </span>
                <span v-else class="text-gray-500 dark:text-gray-400">— Not specified —</span>
              </dd>
            </div>

            <!-- Validity -->
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Validity Period</dt>
              <dd class="mt-1.5 space-y-1 text-gray-900 dark:text-gray-100">
                <div>
                  From: <strong>{{ formatDate(rate.effective_from) || 'Immediate' }}</strong>
                </div>
                <div>
                  To: <strong>{{ formatDate(rate.effective_to) || 'No expiration' }}</strong>
                </div>
                <div class="mt-3">
                  <span
                    :class="{
                      'inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': isActive(),
                      'inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': !isActive()
                    }"
                  >
                    {{ isActive() ? 'Active (as of March 11, 2026)' : 'Inactive' }}
                  </span>
                </div>
              </dd>
            </div>

            <!-- Timestamps -->
            <div class="col-span-full border-t dark:border-gray-700 pt-6 mt-4">
              <div class="grid grid-cols-2 gap-8 text-sm text-gray-600 dark:text-gray-400">
                <div>
                  Created: {{ formatDate(rate.created_at) }}
                </div>
                <div>
                  Last Updated: {{ formatDate(rate.updated_at) }}
                </div>
              </div>
            </div>
          </dl>
        </div>

        <!-- Actions footer -->
        <div class="px-8 py-5 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700 flex justify-end gap-4">
          <Link
            :href="route('admin.rates.index')"
            class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 font-medium"
          >
            Back to Rates
          </Link>
          <Link
            :href="route('admin.rates.edit', rate.id)"
            class="inline-flex items-center px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors"
          >
            Edit This Rate
          </Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
