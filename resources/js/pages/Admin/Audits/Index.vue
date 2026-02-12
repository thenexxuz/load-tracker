<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'

import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
    logs: {
        data: Array<{
            id: number
            description: string
            user: string | null
            model: string
            record_id: number | null
            changes: object | null
            created_at: string
        }>
        links: any[]
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
const modelFilter = ref(props.filters.model || '')

// Live search & filter (preserves pagination)
watch([search, modelFilter], () => {
    router.get(
        route('admin.audits.index'),
        {
            search: search.value,
            model: modelFilter.value,
        },
        { preserveState: true, replace: true }
    )
})

// Show success message from flash
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
    <Head title="Audit Log" />

    <AdminLayout>
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Audit Log
                </h1>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-col sm:flex-row gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search description, user or model..."
                    class="flex-1 border border-gray-300 dark:border-gray-600 rounded-md p-2.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                />

                <input
                    v-model="modelFilter"
                    type="text"
                    placeholder="Filter by model (e.g. User, Carrier)"
                    class="flex-1 border border-gray-300 dark:border-gray-600 rounded-md p-2.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                />
            </div>

            <!-- Empty state -->
            <div
                v-if="!logs.data?.length"
                class="mt-12 text-center py-16 bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700"
            >
                <div class="text-gray-500 dark:text-gray-400 text-lg font-medium">
                    No audit records found
                </div>
                <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
                    System actions will appear here once they occur.
                </p>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md dark:shadow-gray-900/30">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-left">
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Time</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">User</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Action</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Model</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Record ID</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Changes</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ new Date(log.created_at).toLocaleString() }}
                        </td>
                        <td class="px-6 py-4 text-gray-900 dark:text-gray-100 font-medium">
                            {{ log.user || 'System' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ log.description }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 capitalize">
                            {{ log.model }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ log.record_id || '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            <pre v-if="log.changes" class="text-xs bg-gray-50 dark:bg-gray-900 p-2 rounded overflow-auto max-h-32">
                              {{ JSON.stringify(log.changes, null, 2) }}
                            </pre>
                            <span v-else>—</span>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <!-- Pagination (same as Locations) -->
                <div v-if="logs.data?.length" class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-gray-700 dark:text-gray-300">
                    <div>
                        Showing {{ logs.from || 0 }} to {{ logs.to || 0 }} of {{ logs.total || 0 }} audit records
                    </div>

                    <div class="flex items-center space-x-2">
                        <button
                            :disabled="logs.current_page === 1"
                            @click="router.get(route('admin.audits.index', { page: logs.current_page - 1 }), {}, { preserveState: true, preserveScroll: true })"
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Previous
                        </button>

                        <span class="px-4 py-2 font-medium">
                          Page {{ logs.current_page }} of {{ logs.last_page }}
                        </span>

                        <button
                            :disabled="logs.current_page === logs.last_page"
                            @click="router.get(route('admin.audits.index', { page: logs.current_page + 1 }), {}, { preserveState: true, preserveScroll: true })"
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
