<script setup lang="ts">
import AdminLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

defineProps<{
    users: Array<{
        id: number
        name: string
        email: string
        roles: string[]
        edit_url: string
    }>
}>()
</script>

<template>
    <Head title="Manage Users" />

    <AdminLayout>
        <div class="p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
                Users Management
            </h1>

            <!-- Users Table -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md dark:shadow-gray-900/30">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-left">
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Email</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Roles</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="user in users" :key="user.id">
                        <td class="px-6 py-4 text-gray-900 dark:text-gray-100 font-medium">
                            {{ user.name }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ user.email }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ user.roles.join(', ') || 'None' }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a
                                :href="user.edit_url"
                                class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                                title="Edit User"
                            >
                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Optional empty state -->
            <div v-if="!users.length" class="mt-12 text-center text-gray-500 dark:text-gray-400">
                No users found.
            </div>
        </div>
    </AdminLayout>
</template>
