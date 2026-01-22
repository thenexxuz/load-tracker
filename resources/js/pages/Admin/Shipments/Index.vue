<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps<{
    shipments: {
        data: Array<{
            id: number
            status: string
            bol: string | null
            shipment_number: string
            shipper_location: { short_code: string; name: string | null } | null
            dc_location: { short_code: string; name: string | null } | null
            drop_date: string | null
            pickup_date: string | null
            delivery_date: string | null
            carrier: { name: string; short_code: string } | null
            trailer: string | null
        }>
        current_page: number
        last_page: number
        from: number
        to: number
        total: number
    }
    filters: Record<string, any>
}>()

const page = usePage()

const search = ref(props.filters.search || '')

// Live search (preserves pagination)
watch(search, (value) => {
    router.get(route('admin.shipments.index'), { search: value }, { preserveState: true, replace: true })
})

// Delete with SweetAlert2
const destroy = async (id: number) => {
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
        router.delete(route('admin.shipments.destroy', id), {
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
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to delete shipment.'
                })
            },
            preserveScroll: true,
        })
    }
}

// Show flashed success message
onMounted(() => {
    if (page.props.flash?.success) {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: page.props.flash.success,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        })
    }
})

// Format date to YYYY-MM-DD only
const formatDate = (dateString: string | null) => {
    if (!dateString) return '—'
    return dateString.split('T')[0] || '—'
}

// Tooltip: show full original datetime on hover
const getFullDateTime = (dateString: string | null) => {
    if (!dateString) return 'No date/time recorded'
    return dateString
}

// Navigate to Show page when row is clicked
const goToShow = (id: number) => {
    router.visit(route('admin.shipments.show', id))
}
</script>

<template>
    <Head title="Manage Shipments" />

    <AdminLayout>
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Shipments Management
                </h1>
                <a
                    :href="route('admin.shipments.create')"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
                >
                    Add New Shipment
                </a>
            </div>

            <!-- Search -->
            <input
                v-model="search"
                type="text"
                placeholder="Search by shipment number, BOL or PO..."
                class="mb-6 w-full max-w-md border border-gray-300 dark:border-gray-600 rounded-md p-2.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
            />

            <!-- Empty state -->
            <div
                v-if="!shipments.data?.length"
                class="mt-12 text-center py-16 bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700"
            >
                <div class="text-gray-500 dark:text-gray-400 text-lg font-medium">
                    No shipments found
                </div>
                <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
                    Get started by adding a new shipment above.
                </p>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md dark:shadow-gray-900/30">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-left">
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Status</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">BOL</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Shipment Number</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Shipper</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">DC</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Drop Date</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Pickup Date</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Delivery Date</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Carrier</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Trailer</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr
                        v-for="shipment in shipments.data"
                        :key="shipment.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                        @click="goToShow(shipment.id)"
                    >
                        <td class="px-6 py-4 capitalize text-gray-600 dark:text-gray-400">
                            {{ shipment.status }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ shipment.bol || '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-900 dark:text-gray-100 font-medium">
                            {{ shipment.shipment_number }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ shipment.shipper_location?.short_code || '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ shipment.dc_location?.short_code || '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 group relative cursor-help">
                <span :title="getFullDateTime(shipment.drop_date)">
                  {{ formatDate(shipment.drop_date) }}
                </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 group relative cursor-help">
                <span :title="getFullDateTime(shipment.pickup_date)">
                  {{ formatDate(shipment.pickup_date) }}
                </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 group relative cursor-help">
                <span :title="getFullDateTime(shipment.delivery_date)">
                  {{ formatDate(shipment.delivery_date) }}
                </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ shipment.carrier?.name || '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ shipment.trailer || '—' }}
                        </td>
                        <td class="px-6 py-4 text-center space-x-5" @click.stop>
                            <a
                                :href="route('admin.shipments.edit', shipment.id)"
                                class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                                title="Edit Shipment"
                                @click.stop
                            >
                                <svg class="w-5.5 h-5.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>

                            <button
                                @click.stop="destroy(shipment.id)"
                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                title="Delete Shipment"
                            >
                                <svg class="w-5.5 h-5.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="shipments.data?.length" class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-gray-700 dark:text-gray-300">
                    <div>
                        Showing {{ shipments.from || 0 }} to {{ shipments.to || 0 }} of {{ shipments.total || 0 }} shipments
                    </div>

                    <div class="flex items-center space-x-2">
                        <button
                            :disabled="shipments.current_page === 1"
                            @click="router.get(route('admin.shipments.index', { page: shipments.current_page - 1 }), {}, { preserveState: true, preserveScroll: true })"
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Previous
                        </button>

                        <span class="px-4 py-2 font-medium">
              Page {{ shipments.current_page }} of {{ shipments.last_page }}
            </span>

                        <button
                            :disabled="shipments.current_page === shipments.last_page"
                            @click="router.get(route('admin.shipments.index', { page: shipments.current_page + 1 }), {}, { preserveState: true, preserveScroll: true })"
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
