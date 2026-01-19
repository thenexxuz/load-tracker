<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps<{
    locations: {
        data: Array<{
            id: number
            short_code: string
            name: string | null
            type: string
            recycling_location: {
                short_code: string
                name: string | null
            } | null
            is_active: boolean
        }>
    }
    filters: Record<string, any>
}>()

const page = usePage()

const search = ref(props.filters.search || '')

// Live search
watch(search, (value) => {
    router.get(
        route('admin.locations.index'),
        { search: value },
        { preserveState: true, replace: true }
    )
})

// SweetAlert2-powered delete confirmation
const destroy = async (id: number) => {
    const result = await Swal.fire({
        title: 'Delete Location?',
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
        router.delete(route('admin.locations.destroy', id), {
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'Location has been deleted.',
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
                    text: 'Failed to delete location.'
                })
            },
            preserveScroll: true,
        })
    }
}

// Show flashed success message on page load (after create/update/delete redirect)
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
</script>

<template>
    <Head title="Manage Locations" />

    <AdminLayout>
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Locations Management
                </h1>
                <a
                    :href="route('admin.locations.create')"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
                >
                    Add New Location
                </a>
            </div>

            <!-- Search Input -->
            <input
                v-model="search"
                type="text"
                placeholder="Search by name, code or address..."
                class="mb-6 w-full max-w-md border border-gray-300 dark:border-gray-600 rounded-md p-2.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
            />

            <!-- Empty State -->
            <div
                v-if="!locations.data?.length"
                class="mt-12 text-center py-16 bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700"
            >
                <div class="text-gray-500 dark:text-gray-400 text-lg font-medium">
                    No locations found
                </div>
                <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
                    Get started by adding a new location above.
                </p>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md dark:shadow-gray-900/30">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-left">
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Short Code</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Type</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Recycling Location</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Active</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="loc in locations.data" :key="loc.id">
                        <td class="px-6 py-4 text-gray-900 dark:text-gray-100 font-medium">
                            {{ loc.short_code }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ loc.name || '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 capitalize">
                            {{ loc.type.replace('_', ' ') }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ loc.recycling_location?.short_code || '—' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                <span :class="loc.is_active ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 dark:text-red-400 font-medium'">
                  {{ loc.is_active ? 'Yes' : 'No' }}
                </span>
                        </td>
                        <td class="px-6 py-4 text-center space-x-5">
                            <!-- Edit - Pencil Icon -->
                            <a
                                :href="route('admin.locations.edit', loc.id)"
                                class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                                title="Edit Location"
                            >
                                <svg class="w-5.5 h-5.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>

                            <!-- Delete - Trash Icon -->
                            <button
                                @click="destroy(loc.id)"
                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                title="Delete Location"
                            >
                                <svg class="w-5.5 h-5.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
