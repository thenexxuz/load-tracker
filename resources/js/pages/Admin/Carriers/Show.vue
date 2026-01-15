<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
    carrier: {
        id: number
        short_code: string
        wt_code: string | null
        name: string
        emails: string
        is_active: boolean
        created_at: string
        updated_at: string
    }
}>()// Safe computed properties for formatted dates
const formattedCreatedAt = computed(() => {
    return new Date(props.carrier.created_at).toLocaleString()
})

const formattedUpdatedAt = computed(() => {
    return new Date(props.carrier.updated_at).toLocaleString()
})
</script>

<template>
    <Head title="Carrier Details" />

    <AdminLayout>
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Carrier: {{ carrier.name }}
                </h1>
                <div class="space-x-4">
                    <a :href="route('admin.carriers.edit', carrier.id)"
                       class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 font-medium transition-colors">
                        Edit
                    </a>
                    <a href="javascript:history.back()"
                       class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors">
                        ← Back
                    </a>
                </div>
            </div>

            <!-- Carrier Details Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6 space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Short Code</h3>
                            <p class="text-gray-900 dark:text-gray-100 font-medium">{{ carrier.short_code }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">WT Code</h3>
                            <p class="text-gray-900 dark:text-gray-100 font-medium">
                                {{ carrier.wt_code || '—' }}
                            </p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Name</h3>
                            <p class="text-gray-900 dark:text-gray-100 font-medium">{{ carrier.name }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Active</h3>
                            <span :class="carrier.is_active ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 dark:text-red-400 font-medium'">
                {{ carrier.is_active ? 'Yes' : 'No' }}
              </span>
                        </div>
                    </div>

                    <!-- Emails -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Emails</h3>
                        <p class="text-gray-900 dark:text-gray-100 whitespace-pre-line">
                            {{ carrier.emails ? carrier.emails.split(',').map(e => e.trim()).join('\n') : 'None' }}
                        </p>
                    </div>

                    <!-- Timestamps -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Created At</h3>
                            <p class="text-gray-900 dark:text-gray-100">
                                {{ formattedCreatedAt }}
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Updated At</h3>
                            <p class="text-gray-900 dark:text-gray-100">
                                {{ formattedUpdatedAt }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
