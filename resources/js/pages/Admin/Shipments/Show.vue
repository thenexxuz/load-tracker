<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import Swal from 'sweetalert2'

defineProps<{
    shipment: {
        id: number
        shipment_number: string
        bol: string | null
        po_number: string | null
        status: string
        shipper_location: { short_code: string; name: string | null } | null
        dc_location: { short_code: string; name: string | null } | null
        carrier: { name: string; short_code: string } | null
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
        created_at: string
        updated_at: string
    }
}>()

const deleteShipment = async () => {
    const result = await Swal.fire({
        title: 'Delete Shipment?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    })

    if (result.isConfirmed) {
        router.delete(route('admin.shipments.destroy', shipment.id), {
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'Shipment has been deleted.',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                })
                router.visit(route('admin.shipments.index'))
            }
        })
    }
}
</script>

<template>
    <Head title="Shipment Details" />

    <AdminLayout>
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Shipment: {{ shipment.shipment_number }}
                </h1>
                <div class="space-x-4">
                    <a :href="route('admin.shipments.edit', shipment.id)"
                       class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors">
                        Edit
                    </a>
                    <button @click="deleteShipment"
                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors">
                        Delete
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100 capitalize font-medium">{{ shipment.status }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Shipper Location</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ shipment.shipper_location?.short_code || '—' }} - {{ shipment.shipper_location?.name || 'Unnamed' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">DC Location</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ shipment.dc_location?.short_code || '—' }} - {{ shipment.dc_location?.name || 'Unnamed' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Carrier</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ shipment.carrier?.name || '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Drop Date</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.drop_date || '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Pickup Date</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.pickup_date || '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Delivery Date</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.delivery_date || '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Rack Qty</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.rack_qty }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Load Bar Qty</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.load_bar_qty }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Strap Qty</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.strap_qty }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Trailer</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.trailer || '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Drayage</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ shipment.drayage ? 'Yes' : 'No' }}</dd>
                    </div>

                    <!-- Flags -->
                    <div class="col-span-full">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Flags</dt>
                        <dd class="grid grid-cols-2 md:grid-cols-5 gap-4 text-gray-900 dark:text-gray-100">
                            <div>On Site: {{ shipment.on_site ? 'Yes' : 'No' }}</div>
                            <div>Shipped: {{ shipment.shipped ? 'Yes' : 'No' }}</div>
                            <div>Recycling Sent: {{ shipment.recycling_sent ? 'Yes' : 'No' }}</div>
                            <div>Paperwork Sent: {{ shipment.paperwork_sent ? 'Yes' : 'No' }}</div>
                            <div>Delivery Alert Sent: {{ shipment.delivery_alert_sent ? 'Yes' : 'No' }}</div>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Back -->
            <div class="mt-8 text-center">
                <a href="javascript:history.back()" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
                    ← Back to Shipments List
                </a>
            </div>
        </div>
    </AdminLayout>
</template>
