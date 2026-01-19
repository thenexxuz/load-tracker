<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
    carrier: {
        id: number
        short_code: string
        wt_code: string | null
        name: string
        emails: string
        is_active: boolean
    }
}>()

const form = useForm({
    short_code: props.carrier.short_code,
    wt_code: props.carrier.wt_code || '',
    name: props.carrier.name,
    emails: props.carrier.emails || '',
    is_active: props.carrier.is_active,
})

const submit = () => {
    form.put(route('admin.carriers.update', props.carrier.id), {
        onSuccess: () => {
            router.visit(route('admin.carriers.index'), {
                data: { success: 'Carrier updated successfully!' },
                preserveState: true,
            })
        },
        onError: (errors) => {
            console.log('Form errors:', errors)
        },
        onFinish: () => {
            form.processing = false
        }
    })
}
</script>

<template>
    <Head title="Edit Carrier" />

    <AdminLayout>
        <div class="p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
                Edit Carrier: {{ carrier.name }}
            </h1>

            <!-- Error message area -->
            <div
                v-if="Object.keys(form.errors).length"
                class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg"
            >
                Please fix the errors below.
            </div>

            <!-- Form -->
            <form
                @submit.prevent="submit"
                class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 max-w-2xl"
            >
                <!-- Short Code -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Short Code <span class="text-red-600 dark:text-red-400">*</span>
                    </label>
                    <input
                        v-model="form.short_code"
                        type="text"
                        :class="[
              'w-full p-3 border rounded-md focus:ring-2 focus:outline-none',
              form.errors.short_code
                ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
            ]"
                        placeholder="e.g. KN-FTL, SW-REF"
                        required
                    />
                    <p v-if="form.errors.short_code" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.short_code }}
                    </p>
                </div>

                <!-- WT Code (NEW FIELD) -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        WT Code
                    </label>
                    <input
                        v-model="form.wt_code"
                        type="text"
                        :class="[
              'w-full p-3 border rounded-md focus:ring-2 focus:outline-none',
              form.errors.wt_code
                ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
            ]"
                        placeholder="e.g. WT-12345 or leave blank"
                    />
                    <p v-if="form.errors.wt_code" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.wt_code }}
                    </p>
                </div>

                <!-- Name -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Carrier Name <span class="text-red-600 dark:text-red-400">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :class="[
              'w-full p-3 border rounded-md focus:ring-2 focus:outline-none',
              form.errors.name
                ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
            ]"
                        placeholder="e.g. Knight-Swift Transportation"
                        required
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Emails -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Emails (comma-separated)
                    </label>
                    <textarea
                        v-model="form.emails"
                        rows="3"
                        :class="[
              'w-full p-3 border rounded-md focus:ring-2 focus:outline-none resize-none',
              form.errors.emails
                ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
            ]"
                        placeholder="dispatcher@carrier.com, ops@carrier.com, billing@carrier.com"
                    ></textarea>
                    <p v-if="form.errors.emails" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.emails }}
                    </p>
                </div>

                <!-- Active Toggle -->
                <div class="mb-8">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="h-5 w-5 text-green-600 focus:ring-green-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700"
                        />
                        <span class="text-gray-700 dark:text-gray-300 font-medium">
              Active Carrier
            </span>
                    </label>
                </div>

                <!-- Submit & Cancel -->
                <div class="flex justify-end space-x-4">
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
