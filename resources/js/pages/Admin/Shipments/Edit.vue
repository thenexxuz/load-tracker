<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps<{
    shipment: {
        id: number
        shipment_number: string
        bol: string | null
        po_number: string | null
        status: string
        shipper_location_id: number
        dc_location_id: number | null
        carrier_id: number | null
        drop_date: string | null
        pickup_date: string | null
        delivery_date: string | null
        rack_qty: number
        load_bar_qty: number
        strap_qty: number
        trailer: string | null
        drayage: boolean
        on_site: boolean
        shipped: boolean
        recycling_sent: boolean
        paperwork_sent: boolean
        delivery_alert_sent: boolean
    }
    pickupLocations: Array<{ id: number; short_code: string; name: string | null }>
    dcLocations: Array<{ id: number; short_code: string; name: string | null }>
    carriers: Array<{ id: number; name: string; short_code: string }>
}>()

const form = useForm({
    shipment_number: props.shipment.shipment_number,
    bol: props.shipment.bol || '',
    po_number: props.shipment.po_number || '',
    status: props.shipment.status,
    shipper_location_id: props.shipment.shipper_location_id,
    dc_location_id: props.shipment.dc_location_id ?? null,
    carrier_id: props.shipment.carrier_id ?? null,
    drop_date: props.shipment.drop_date,
    pickup_date: props.shipment.pickup_date,
    delivery_date: props.shipment.delivery_date,
    rack_qty: props.shipment.rack_qty,
    load_bar_qty: props.shipment.load_bar_qty,
    strap_qty: props.shipment.strap_qty,
    trailer: props.shipment.trailer || '',
    drayage: props.shipment.drayage,
    on_site: props.shipment.on_site,
    shipped: props.shipment.shipped,
    recycling_sent: props.shipment.recycling_sent,
    paperwork_sent: props.shipment.paperwork_sent,
    delivery_alert_sent: props.shipment.delivery_alert_sent,
})

const submit = () => {
    form.put(route('admin.shipments.update', props.shipment.id), {
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
                Edit Shipment: {{ shipment.shipment_number }}
            </h1>

            <!-- Error banner -->
            <div v-if="Object.keys(form.errors).length" class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg">
                Please fix the errors below.
            </div>

            <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 max-w-3xl">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Shipment Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Shipment Number <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <input v-model="form.shipment_number" type="text" required class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" />
                        <p v-if="form.errors.shipment_number" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.shipment_number }}</p>
                    </div>

                    <!-- BOL -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">BOL</label>
                        <input v-model="form.bol" type="text" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" placeholder="BOL-987654" />
                        <p v-if="form.errors.bol" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.bol }}</p>
                    </div>

                    <!-- PO Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">PO Number</label>
                        <input v-model="form.po_number" type="text" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" placeholder="PO-456789" />
                        <p v-if="form.errors.po_number" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.po_number }}</p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <select v-model="form.status" required class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500">
                            <option value="pending">Pending</option>
                            <option value="picked_up">Picked Up</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <p v-if="form.errors.status" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.status }}</p>
                    </div>

                    <!-- Shipper Location (only pickup) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Shipper Location <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <select v-model="form.shipper_location_id" required class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500">
                            <option :value="null" disabled>Select Pickup Location</option>
                            <option v-for="loc in pickupLocations" :key="loc.id" :value="loc.id">
                                {{ loc.short_code }} - {{ loc.name || 'Unnamed' }}
                            </option>
                        </select>
                        <p v-if="form.errors.shipper_location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.shipper_location_id }}</p>
                    </div>

                    <!-- DC Location (only distribution_center) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">DC Location</label>
                        <select v-model="form.dc_location_id" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500">
                            <option :value="null">None / Not Applicable</option>
                            <option v-for="loc in dcLocations" :key="loc.id" :value="loc.id">
                                {{ loc.short_code }} - {{ loc.name || 'Unnamed' }}
                            </option>
                        </select>
                        <p v-if="form.errors.dc_location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.dc_location_id }}</p>
                    </div>

                    <!-- Carrier -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Carrier</label>
                        <select v-model="form.carrier_id" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500">
                            <option :value="null">None</option>
                            <option v-for="carrier in carriers" :key="carrier.id" :value="carrier.id">
                                {{ carrier.short_code }} - {{ carrier.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.carrier_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.carrier_id }}</p>
                    </div>

                    <!-- Dates -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Drop Date</label>
                        <input v-model="form.drop_date" type="date" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" />
                        <p v-if="form.errors.drop_date" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.drop_date }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pickup Date</label>
                        <input v-model="form.pickup_date" type="datetime-local" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" />
                        <p v-if="form.errors.pickup_date" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.pickup_date }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delivery Date</label>
                        <input v-model="form.delivery_date" type="datetime-local" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" />
                        <p v-if="form.errors.delivery_date" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.delivery_date }}</p>
                    </div>

                    <!-- Quantities -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Rack Qty</label>
                        <input v-model="form.rack_qty" type="number" min="0" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" />
                        <p v-if="form.errors.rack_qty" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.rack_qty }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Load Bar Qty</label>
                        <input v-model="form.load_bar_qty" type="number" min="0" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" />
                        <p v-if="form.errors.load_bar_qty" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.load_bar_qty }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Strap Qty</label>
                        <input v-model="form.strap_qty" type="number" min="0" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" />
                        <p v-if="form.errors.strap_qty" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.strap_qty }}</p>
                    </div>

                    <!-- Trailer & Drayage -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Trailer</label>
                        <input v-model="form.trailer" type="text" class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500" placeholder="e.g. TRAILER-9876" />
                        <p v-if="form.errors.trailer" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.trailer }}</p>
                    </div>

                    <div class="flex items-center">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.drayage" class="h-5 w-5 text-blue-600 rounded" />
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Drayage</span>
                        </label>
                    </div>

                    <!-- Flags -->
                    <div class="col-span-full grid grid-cols-2 md:grid-cols-5 gap-6">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.on_site" class="h-5 w-5 text-blue-600 rounded" />
                            <span class="text-gray-700 dark:text-gray-300 font-medium">On Site</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.shipped" class="h-5 w-5 text-blue-600 rounded" />
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Shipped</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.recycling_sent" class="h-5 w-5 text-blue-600 rounded" />
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Recycling Sent</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.paperwork_sent" class="h-5 w-5 text-blue-600 rounded" />
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Paperwork Sent</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.delivery_alert_sent" class="h-5 w-5 text-blue-600 rounded" />
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Delivery Alert Sent</span>
                        </label>
                    </div>
                </div>

                <!-- Submit & Cancel -->
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="javascript:history.back()"
                       class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
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
