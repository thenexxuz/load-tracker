<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { inject, ref } from 'vue'

import AdminLayout from '@/layouts/AppLayout.vue'

const route = inject('route')!

const props = defineProps<{
    roles: any[]
    permissions: string[]
}>()

// Create form
const form = useForm({
    name: '',
    permissions: [] as string[]
})

const successMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

// Submit create form
const submit = () => {
    successMessage.value = null
    errorMessage.value = null

    form.post(route('admin.roles.store'), {
        onSuccess: () => {
            form.reset('name', 'permissions')
            successMessage.value = 'Role created successfully!'
        },
        onError: (errors) => {
            errorMessage.value = 'Please fix the errors below.'
            console.log('Form errors:', errors)
        },
        onFinish: () => {
            form.processing = false
        }
    })
}

// ── Edit Modal ──────────────────────────────────────────────
const showEditModal = ref(false)
const editingRole = ref<any | null>(null)
const editForm = useForm({
    name: '',
    permissions: [] as string[]
})

const openEditModal = (role: any) => {
    editingRole.value = role
    editForm.name = role.name
    editForm.permissions = role.permissions.map((p: any) => p.name)
    showEditModal.value = true
}

const submitEdit = () => {
    if (!editingRole.value) return

    editForm.put(route('admin.roles.update', editingRole.value.id), {
        onSuccess: () => {
            showEditModal.value = false
            editingRole.value = null
            successMessage.value = 'Role updated successfully!'
        },
        onError: (errors) => {
            errorMessage.value = 'Please fix the errors below.'
            console.log('Edit errors:', errors)
        },
        onFinish: () => {
            editForm.processing = false
        }
    })
}

const cancelEdit = () => {
    showEditModal.value = false
    editingRole.value = null
    editForm.reset()
}

// ── Delete Modal ────────────────────────────────────────────
const showDeleteModal = ref(false)
const roleToDelete = ref<any | null>(null)

const openDeleteModal = (role: any) => {
    roleToDelete.value = role
    showDeleteModal.value = true
}

const confirmDelete = () => {
    if (!roleToDelete.value) return

    router.delete(route('admin.roles.destroy', roleToDelete.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false
            roleToDelete.value = null
            successMessage.value = 'Role deleted successfully!'
        },
        onError: (errors) => {
            errorMessage.value = 'Failed to delete role. It may be in use.'
            console.error('Delete error:', errors)
        },
        preserveScroll: true,
    })
}

const cancelDelete = () => {
    showDeleteModal.value = false
    roleToDelete.value = null
}
</script>

<template>
    <Head title="Manage Roles" />

    <AdminLayout>
        <div class="p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
                Roles Management
            </h1>

            <!-- Messages -->
            <div v-if="successMessage" class="mb-6 p-4 bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200 rounded-lg">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage || Object.keys(form.errors).length || Object.keys(editForm.errors).length" class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg">
                {{ errorMessage || 'Please check the form for errors.' }}
            </div>

            <!-- Create Role Form -->
            <form
                @submit.prevent="submit"
                class="mb-10 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700"
            >
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Role Name
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :class="[
              'border p-2 w-full rounded-md focus:ring-2 focus:border-blue-500 focus:outline-none',
              form.errors.name
                ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100'
            ]"
                        required
                        placeholder="e.g. manager"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Permissions
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <label
                            v-for="perm in permissions"
                            :key="perm"
                            class="flex items-center space-x-2 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                v-model="form.permissions"
                                :value="perm"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ perm }}</span>
                        </label>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white px-5 py-2.5 rounded-md font-medium transition-colors disabled:opacity-50"
                >
                    {{ form.processing ? 'Creating...' : 'Create Role' }}
                </button>
            </form>

            <!-- Roles Table -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md dark:shadow-gray-900/30">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-left">
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Permissions</th>
                        <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="role in roles" :key="role.id">
                        <td class="px-6 py-4 text-gray-900 dark:text-gray-100 font-medium">
                            {{ role.name }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            {{ role.permissions.map((p: any) => p.name).join(', ') || 'None' }}
                        </td>
                        <td class="px-6 py-4 text-right space-x-4">
                            <!-- Edit button -->
                            <button
                                @click="openEditModal(role)"
                                class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                                title="Edit Role"
                            >
                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>

                            <!-- Delete button -->
                            <button
                                @click="openDeleteModal(role)"
                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                title="Delete Role"
                            >
                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Edit Modal -->
            <div
                v-if="showEditModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm transition-opacity duration-300"
                @click.self="cancelEdit"
            >
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-md w-full mx-4 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Edit Role
                    </h3>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Role Name
                        </label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            :class="[
                'border p-2 w-full rounded-md focus:ring-2 focus:outline-none',
                editForm.errors.name
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100'
              ]"
                            required
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ editForm.errors.name }}
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Permissions
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <label
                                v-for="perm in props.permissions"
                                :key="perm"
                                class="flex items-center space-x-2 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :value="perm"
                                    v-model="editForm.permissions"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ perm }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            @click="cancelEdit"
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submitEdit"
                            :disabled="editForm.processing"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800 text-white rounded-md disabled:opacity-50 transition-colors"
                        >
                            {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div
                v-if="showDeleteModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm transition-opacity duration-300"
                @click.self="cancelDelete"
            >
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl max-w-md w-full mx-4 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Confirm Deletion
                    </h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-6">
                        Are you sure you want to delete the role
                        <span class="font-medium text-red-600 dark:text-red-400">"{{ roleToDelete?.name }}"</span>?<br />
                        This action cannot be undone.
                    </p>

                    <div class="flex justify-end space-x-3">
                        <button
                            @click="cancelDelete"
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmDelete"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800 text-white rounded-md disabled:opacity-50 transition-colors"
                        >
                            Delete Role
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
