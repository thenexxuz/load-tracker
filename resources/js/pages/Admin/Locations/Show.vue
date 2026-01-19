<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
    location: any
}>()
</script>

<template>
    <Head title="Location Details" />

    <AdminLayout>
        <div class="p-6">
            <h1 class="text-2xl font-bold mb-6">Location Details</h1>

            <!-- ... other fields ... -->

            <!-- Recycling Locations (many) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Recycling Locations
                </label>
                <p v-if="!location.recycling_locations.length" class="text-gray-600 dark:text-gray-400">
                    None
                </p>
                <ul v-else class="list-disc pl-5">
                    <p>Recycling Location: {{ location.recycling_location?.short_code || 'None' }}</p>
                </ul>
            </div>

            <!-- Submit or Back -->
            <div class="flex justify-end mt-8">
                <a :href="route('admin.locations.edit', location.id)" class="text-blue-600">
                    Edit
                </a>
            </div>
        </div>
    </AdminLayout>
</template>
