<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  pickupLocations: Array<{ id: number; short_code: string; name: string | null }>
  dcLocations: Array<{ id: number; short_code: string; name: string | null }>
  carriers: Array<{ id: number; name: string; short_code: string }>
}>()

const form = useForm({
  status: 'Pending',
  bol: '',
  shipment_number: null as number | null,
  pickup_location_id: null as number | null,
  dc_location_id: null as number | null,
  drop_date: null as string | null,
  pickup_date: null as string | null,
  delivery_date: null as string | null,
  po_number: '',
  rack_qty: 0,
  carrier_id: null as number | null,
  trailer: '',
  load_bar_qty: 0,
  strap_qty: 0,
  drayage: '',
  on_site: null as string | null,
  shipped: null as string | null,
  crossed: null as string | null,          // new
  recycling_sent: null as string | null,
  paperwork_sent: null as string | null,
  delivery_alert_sent: null as string | null,
  seal_number: '',                         // new
  drivers_id: '',                          // new
  consolidation_number: '',
  notes: '',
  other: '',
})

// Helper to set current timestamp when user "checks" a flag
const setCurrentDate = (field: keyof typeof form) => {
  form[field] = new Date().toISOString()
}

const clearDate = (field: keyof typeof form) => {
  form[field] = null
}

const submit = () => {
  form.post(route('admin.shipments.store'), {
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Created!',
        text: 'Shipment created successfully.',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
      })
      form.reset()
    },
    onError: () => {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Please fix the errors in the form.'
      })
    }
  })
}
</script>

<template>
  <Head title="Create Shipment" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Create New Shipment
      </h1>

      <!-- Error banner -->
      <div v-if="Object.keys(form.errors).length" class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg">
        Please fix the errors below.
      </div>

      <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 max-w-5xl">
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
              <option value="Checked In">Checked In</option>
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

          <!-- Shipper Location -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Shipper Location <span class="text-red-600 dark:text-red-400">*</span>
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

          <!-- Seal Number (new) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Seal Number</label>
            <input
              v-model="form.seal_number"
              type="text"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.seal_number" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.seal_number }}
            </p>
          </div>

          <!-- Drivers ID (new) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Drivers ID</label>
            <input
              v-model="form.drivers_id"
              type="text"
              class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
            />
            <p v-if="form.errors.drivers_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.drivers_id }}
            </p>
          </div>

          <!-- Flags as datetime-local inputs -->
          <div class="col-span-full grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">On Site</label>
              <input
                v-model="form.on_site"
                type="datetime-local"
                class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
              />
              <p v-if="form.errors.on_site" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.on_site }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Shipped</label>
              <input
                v-model="form.shipped"
                type="datetime-local"
                class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
              />
              <p v-if="form.errors.shipped" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.shipped }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Crossed</label>
              <input
                v-model="form.crossed"
                type="datetime-local"
                class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
              />
              <p v-if="form.errors.crossed" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.crossed }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recycling Sent</label>
              <input
                v-model="form.recycling_sent"
                type="datetime-local"
                class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
              />
              <p v-if="form.errors.recycling_sent" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.recycling_sent }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Paperwork Sent</label>
              <input
                v-model="form.paperwork_sent"
                type="datetime-local"
                class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
              />
              <p v-if="form.errors.paperwork_sent" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.paperwork_sent }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delivery Alert Sent</label>
              <input
                v-model="form.delivery_alert_sent"
                type="datetime-local"
                class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
              />
              <p v-if="form.errors.delivery_alert_sent" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.delivery_alert_sent }}
              </p>
            </div>
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
            class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-md font-medium transition-colors disabled:opacity-50"
          >
            {{ form.processing ? 'Creating...' : 'Create Shipment' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
