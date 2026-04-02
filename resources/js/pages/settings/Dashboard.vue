<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import HeadingSmall from '@/components/HeadingSmall.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import { type BreadcrumbItem } from '@/types'
import { route } from 'ziggy-js'

const props = defineProps<{
  dashboardPreferences: {
    sections: {
      booked_shipments: boolean
      deliveries_chart: boolean
      monitored_locations: boolean
      active_shipments_by_carrier: boolean
      shipment_offers_by_user: boolean
    }
    monitored_location_ids: string[]
  }
  availableMonitoredLocations: Array<{
    id: string
    name: string | null
    short_code: string | null
    type: string
    inbound: boolean
    outbound: boolean
  }>
}>()

const form = useForm({
  sections: {
    booked_shipments: props.dashboardPreferences.sections.booked_shipments,
    deliveries_chart: props.dashboardPreferences.sections.deliveries_chart,
    monitored_locations: props.dashboardPreferences.sections.monitored_locations,
    active_shipments_by_carrier: props.dashboardPreferences.sections.active_shipments_by_carrier,
    shipment_offers_by_user: props.dashboardPreferences.sections.shipment_offers_by_user,
  },
  monitored_location_ids: props.dashboardPreferences.monitored_location_ids,
})

const submit = (): void => {
  form.patch(route('dashboard-preferences.update'), {
    preserveScroll: true,
  })
}

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Dashboard settings',
    href: route('dashboard-preferences.edit'),
  },
]
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Dashboard settings" />

    <h1 class="sr-only">Dashboard Settings</h1>

    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Dashboard Settings"
          description="Choose which dashboard sections are visible and which locations are monitored."
        />

        <div class="grid gap-4 md:grid-cols-2">
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input v-model="form.sections.booked_shipments" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>Booked Shipments</span>
          </label>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input v-model="form.sections.deliveries_chart" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>Deliveries Chart</span>
          </label>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input v-model="form.sections.monitored_locations" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>Monitored Locations</span>
          </label>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input v-model="form.sections.active_shipments_by_carrier" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>Active Shipments by Carrier</span>
          </label>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input v-model="form.sections.shipment_offers_by_user" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>Shipment Offers by User</span>
          </label>
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
            Monitored Locations
          </label>
          <select
            v-model="form.monitored_location_ids"
            multiple
            class="w-full min-h-44 rounded-md border border-gray-300 bg-white p-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          >
            <option
              v-for="locationOption in availableMonitoredLocations"
              :key="locationOption.id"
              :value="locationOption.id"
            >
              {{ locationOption.short_code || '—' }} - {{ locationOption.name || 'Unnamed Location' }}
              [{{ locationOption.outbound ? 'Outbound' : locationOption.inbound ? 'Inbound' : 'None' }}]
            </option>
          </select>
          <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Outbound locations are calculated using pickup shipments. Inbound locations are calculated using DC shipments.
          </p>
        </div>

        <div class="flex justify-end">
          <button
            type="button"
            :disabled="form.processing"
            class="rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
            @click="submit"
          >
            {{ form.processing ? 'Saving...' : 'Save Dashboard Settings' }}
          </button>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
