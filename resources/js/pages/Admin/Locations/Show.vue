<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

import AdminLayout from '@/layouts/AppLayout.vue'


const props = defineProps<{
    location: {
        id: number
        short_code: string
        name: string | null
        address: string
        city: string | null
        state: string | null
        zip: string | null
        country: string
        type: 'pickup' | 'distribution_center' | 'recycling'
        latitude: number | null
        longitude: number | null
        is_active: boolean
        recycling_location: { short_code: string; name: string | null } | null
        email: string | null
        expected_arrival_time: string | null
        created_at: string
        updated_at: string
    }
}>()

// Delete confirmation with SweetAlert2
const deleteLocation = async () => {
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
        router.delete(route('admin.locations.destroy', props.location.id), {
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
                router.visit(route('admin.locations.index'))
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
</script>

<template>
    <Head title="Location Details" />

    <AdminLayout>
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Location: {{ location.short_code }}
                </h1>
                <div class="space-x-4">
                    <a
                        :href="route('admin.locations.edit', location.id)"
                        class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors font-medium"
                    >
                        Edit
                    </a>
                    <button
                        @click="deleteLocation"
                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors font-medium"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <!-- Main Details Card -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700">
                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Short Code -->
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Short Code</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ location.short_code }}
                        </dd>
                    </div>

                    <!-- Name -->
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ location.name || '—' }}
                        </dd>
                    </div>

                    <!-- Type -->
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100 capitalize">
                            {{ location.type.replace('_', ' ') }}
                        </dd>
                    </div>

                    <!-- Address -->
                    <div class="md:col-span-2 lg:col-span-3">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Full Address</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ location.address || '—' }}
                            <br v-if="location.city || location.state || location.zip" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ [location.city, location.state ? `${location.state} ${location.zip}` : null, location.country].filter(Boolean).join(', ') || '' }}
              </span>
                        </dd>
                    </div>

                    <!-- Coordinates -->
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Latitude</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ location.latitude || '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Longitude</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ location.longitude || '—' }}</dd>
                    </div>

                    <!-- Email -->
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ location.email || '—' }}
                        </dd>
                    </div>

                    <!-- Expected Arrival Time -->
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expected Arrival Time</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ location.expected_arrival_time || '—' }}
                        </dd>
                    </div>

                    <!-- Recycling Location (only shown if DC) -->
                    <div v-if="location.type === 'distribution_center'">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Recycling Location</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ location.recycling_location?.short_code || '—' }}
                            <span v-if="location.recycling_location?.name" class="text-sm text-gray-500 dark:text-gray-400">
                - {{ location.recycling_location.name }}
              </span>
                        </dd>
                    </div>

                    <!-- Active Status -->
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</dt>
                        <dd class="mt-1">
              <span :class="location.is_active ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 dark:text-red-400 font-medium'">
                {{ location.is_active ? 'Yes' : 'No' }}
              </span>
                        </dd>
                    </div>

                    <!-- Timestamps -->
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ new Date(location.created_at).toLocaleString() }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated At</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ new Date(location.updated_at).toLocaleString() }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Back Link -->
            <div class="mt-8 text-center">
                <a href="javascript:history.back()" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
                    ← Back to Locations List
                </a>
            </div>
        </div>
    </AdminLayout>
</template>
