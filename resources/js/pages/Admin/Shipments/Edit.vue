<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps<{
  shipment: {
    id: number
    status: string
    bol: string | null
    shipment_number: number | null
    shipper_location_id: number
    dc_location_id: number
    drop_date: string | null
    pickup_date: string | null
    delivery_date: string | null
    po_number: string | null
    rack_qty: number
    carrier_id: number | null
    trailer: string | null
    load_bar_qty: number
    strap_qty: number
    drayage: string | null
    on_site: string | null
    shipped: string | null
    crossed_border: string | null
    recycling_sent: string | null
    paperwork_sent: string | null
    delivery_sent: string | null
    consolidation_number: string | null
    notes: string | null
    other: object | null | string
  }
  pickupLocations: Array<{ id: number; short_code: string; name: string | null }>
  dcLocations: Array<{ id: number; short_code: string; name: string | null }>
  carriers: Array<{ id: number; name: string; short_code: string }>
}>()

const form = useForm({
  status: props.shipment.status || 'Pending',
  bol: props.shipment.bol || '',
  shipment_number: props.shipment.shipment_number,
  shipper_location_id: props.shipment.shipper_location_id,
  dc_location_id: props.shipment.dc_location_id,
  drop_date: props.shipment.drop_date || null,
  pickup_date: props.shipment.pickup_date || null,
  delivery_date: props.shipment.delivery_date || null,
  po_number: props.shipment.po_number || '',
  rack_qty: props.shipment.rack_qty || 0,
  carrier_id: props.shipment.carrier_id || null,
  trailer: props.shipment.trailer || '',
  load_bar_qty: props.shipment.load_bar_qty || 0,
  strap_qty: props.shipment.strap_qty || 0,
  drayage: props.shipment.drayage || '',
  on_site_checked: !!props.shipment.on_site,
  shipped_checked: !!props.shipment.shipped,
  crossed_border: props.shipment.crossed_border || null,
  recycling_sent_checked: !!props.shipment.recycling_sent,
  paperwork_sent_checked: !!props.shipment.paperwork_sent,
  delivery_sent_checked: !!props.shipment.delivery_sent,
  consolidation_number: props.shipment.consolidation_number || '',
  notes: props.shipment.notes || '',
  other: props.shipment.other ? JSON.stringify(props.shipment.other, null, 2) : '',
})

const submit = () => {
  const payload = {
    ...form.data(),
    on_site: form.on_site_checked 
      ? (props.shipment.on_site ? props.shipment.on_site : new Date().toISOString()) 
      : null,
    shipped: form.shipped_checked 
      ? (props.shipment.shipped ? props.shipment.shipped : new Date().toISOString()) 
      : null,
    recycling_sent: form.recycling_sent_checked 
      ? (props.shipment.recycling_sent ? props.shipment.recycling_sent : new Date().toISOString()) 
      : null,
    paperwork_sent: form.paperwork_sent_checked 
      ? (props.shipment.paperwork_sent ? props.shipment.paperwork_sent : new Date().toISOString()) 
      : null,
    delivery_sent: form.delivery_sent_checked 
      ? (props.shipment.delivery_sent ? props.shipment.delivery_sent : new Date().toISOString()) 
      : null,
    // Remove temporary checked fields
    on_site_checked: undefined,
    shipped_checked: undefined,
    recycling_sent_checked: undefined,
    paperwork_sent_checked: undefined,
    delivery_sent_checked: undefined,
  }

  form.put(route('admin.shipments.update', props.shipment.id), {
    data: payload,
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Shipment updated successfully.',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
      })
    },
    onError: () => {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Please fix the errors in the form.'
      })
    },
    onFinish: () => {
      form.processing = false
    }
  })
}
</script>

<template>
  <Head title="Edit Shipment" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Edit Shipment: {{ shipment.shipment_number || 'Unnamed' }}
      </h1>

      <!-- Error banner -->
      <div v-if="Object.keys(form.errors).length" class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg">
        Please fix the errors below.
      </div>

      <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 max-w-4xl">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Status <span class="text-red-600 dark:text-red-400">*</span>
            </label>
            <select
              v-model="form.status"
              required
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            >
              <option value="Pending">Pending</option>
              <option value="Picked Up">Picked Up</option>
              <option value="In Transit">In Transit</option>
              <option value="Crossed Border">Crossed Border</option>
              <option value="Delivered">Delivered</option>
              <option value="Cancelled">Cancelled</option>
            </select>
            <p v-if="form.errors.status" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.status }}
            </p>
          </div>

          <!-- BOL -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">BOL</label>
            <input
              v-model="form.bol"
              type="text"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.bol" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.bol }}
            </p>
          </div>

          <!-- Shipment Number -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Shipment Number</label>
            <input
              v-model.number="form.shipment_number"
              type="number"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.shipment_number" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.shipment_number }}
            </p>
          </div>

          <!-- Shipper Location (only pickup type) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Shipper Location <span class="text-red-600 dark:text-red-400">*</span>
            </label>
            <select
              v-model="form.shipper_location_id"
              required
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            >
              <option :value="null" disabled>Select Pickup Location</option>
              <option v-for="loc in pickupLocations" :key="loc.id" :value="loc.id">
                {{ loc.short_code }} - {{ loc.name || 'Unnamed' }}
              </option>
            </select>
            <p v-if="form.errors.shipper_location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.shipper_location_id }}
            </p>
          </div>

          <!-- DC Location (only distribution_center type) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              DC Location <span class="text-red-600 dark:text-red-400">*</span>
            </label>
            <select
              v-model="form.dc_location_id"
              required
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            >
              <option :value="null" disabled>Select Distribution Center</option>
              <option v-for="loc in dcLocations" :key="loc.id" :value="loc.id">
                {{ loc.short_code }} - {{ loc.name || 'Unnamed' }}
              </option>
            </select>
            <p v-if="form.errors.dc_location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.dc_location_id }}
            </p>
          </div>

          <!-- Carrier -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Carrier</label>
            <select
              v-model="form.carrier_id"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            >
              <option :value="null">None</option>
              <option v-for="carrier in carriers" :key="carrier.id" :value="carrier.id">
                {{ carrier.short_code }} - {{ carrier.name }}
              </option>
            </select>
            <p v-if="form.errors.carrier_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.carrier_id }}
            </p>
          </div>

          <!-- Drop Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Drop Date</label>
            <input
              v-model="form.drop_date"
              type="date"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.drop_date" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.drop_date }}
            </p>
          </div>

          <!-- Pickup Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pickup Date</label>
            <input
              v-model="form.pickup_date"
              type="datetime-local"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.pickup_date" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.pickup_date }}
            </p>
          </div>

          <!-- Delivery Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delivery Date</label>
            <input
              v-model="form.delivery_date"
              type="datetime-local"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.delivery_date" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.delivery_date }}
            </p>
          </div>

          <!-- Rack Qty -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Rack Qty</label>
            <input
              v-model.number="form.rack_qty"
              type="number"
              min="0"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.rack_qty" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.rack_qty }}
            </p>
          </div>

          <!-- Load Bar Qty -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Load Bar Qty</label>
            <input
              v-model.number="form.load_bar_qty"
              type="number"
              min="0"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.load_bar_qty" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.load_bar_qty }}
            </p>
          </div>

          <!-- Strap Qty -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Strap Qty</label>
            <input
              v-model.number="form.strap_qty"
              type="number"
              min="0"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.strap_qty" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.strap_qty }}
            </p>
          </div>

          <!-- Trailer -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Trailer</label>
            <input
              v-model="form.trailer"
              type="text"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.trailer" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.trailer }}
            </p>
          </div>

          <!-- Drayage -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Drayage</label>
            <input
              v-model="form.drayage"
              type="text"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
              placeholder="e.g. Yes/No or details"
            />
            <p v-if="form.errors.drayage" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.drayage }}
            </p>
          </div>

          <!-- Checkboxes for datetime flags -->
          <div class="col-span-full grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
            <label class="flex items-center space-x-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.on_site_checked"
                class="h-5 w-5 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500"
              />
              <span class="text-gray-700 dark:text-gray-300">On Site</span>
            </label>

            <label class="flex items-center space-x-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.shipped_checked"
                class="h-5 w-5 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500"
              />
              <span class="text-gray-700 dark:text-gray-300">Shipped</span>
            </label>

            <label class="flex items-center space-x-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.recycling_sent_checked"
                class="h-5 w-5 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500"
              />
              <span class="text-gray-700 dark:text-gray-300">Recycling Sent</span>
            </label>

            <label class="flex items-center space-x-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.paperwork_sent_checked"
                class="h-5 w-5 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500"
              />
              <span class="text-gray-700 dark:text-gray-300">Paperwork Sent</span>
            </label>

            <label class="flex items-center space-x-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.delivery_sent_checked"
                class="h-5 w-5 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500"
              />
              <span class="text-gray-700 dark:text-gray-300">Delivery Alert Sent</span>
            </label>
          </div>

          <!-- Consolidation Number -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Consolidation Number</label>
            <input
              v-model="form.consolidation_number"
              type="text"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.consolidation_number" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.consolidation_number }}
            </p>
          </div>

          <!-- Notes -->
          <div class="md:col-span-2 lg:col-span-3">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
            <textarea
              v-model="form.notes"
              rows="4"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
              placeholder="Additional notes..."
            ></textarea>
            <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.notes }}
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
            class="bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600 text-white px-8 py-3 rounded-md font-medium transition-colors disabled:opacity-50"
          >
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>