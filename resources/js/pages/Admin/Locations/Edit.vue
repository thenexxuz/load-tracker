<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue'
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
        recycling_location_id: number | null
        latitude: number | null
        longitude: number | null
        is_active: boolean
    }
    availableRecyclingLocations: Array<{
        id: number
        short_code: string
        name: string | null
    }>
}>()

const form = useForm({
    short_code: props.location.short_code,
    name: props.location.name || '',
    address: props.location.address,
    city: props.location.city || '',
    state: props.location.state || '',
    zip: props.location.zip || '',
    country: props.location.country,
    type: props.location.type,
    latitude: props.location.latitude,
    longitude: props.location.longitude,
    is_active: props.location.is_active,
    recycling_location_id: props.location.recycling_location_id ?? null,
})

// Reset recycling_location_id when type changes away from distribution_center
watch(
    () => form.type,
    (newType) => {
        if (newType !== 'distribution_center') {
            form.recycling_location_id = null
        }
    }
)

const submit = () => {
    form.put(route('admin.locations.update', props.location.id), {
        onSuccess: () => {
            router.visit(route('admin.locations.index'), {
                data: { success: 'Location updated successfully!' },
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
    <Head title="Edit Location" />

    <AdminLayout>
        <div class="p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
                Edit Location: {{ location.short_code }}
            </h1>

            <!-- Error message banner -->
            <div
                v-if="Object.keys(form.errors).length"
                class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg"
            >
                Please fix the errors below.
            </div>

            <!-- Main Form -->
            <form
                @submit.prevent="submit"
                class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700 max-w-3xl"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Short Code -->
                    <div>
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
                            placeholder="e.g. DAL-P, HOU-DC, AUS-R"
                            required
                        />
                        <p v-if="form.errors.short_code" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.short_code }}
                        </p>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Location Name
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
                            placeholder="e.g. Dallas Distribution Center"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Address -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Full Address <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <textarea
                            v-model="form.address"
                            rows="2"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none resize-none',
                form.errors.address
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                            placeholder="123 Freight Lane, Suite 100"
                            required
                        ></textarea>
                        <p v-if="form.errors.address" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.address }}
                        </p>
                    </div>

                    <!-- City -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">City</label>
                        <input
                            v-model="form.city"
                            type="text"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none',
                form.errors.city
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                            placeholder="Dallas"
                        />
                        <p v-if="form.errors.city" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.city }}
                        </p>
                    </div>

                    <!-- State -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">State</label>
                        <input
                            v-model="form.state"
                            type="text"
                            maxlength="2"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none',
                form.errors.state
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                            placeholder="TX"
                        />
                        <p v-if="form.errors.state" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.state }}
                        </p>
                    </div>

                    <!-- Zip -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Zip</label>
                        <input
                            v-model="form.zip"
                            type="text"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none',
                form.errors.zip
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                            placeholder="75201"
                        />
                        <p v-if="form.errors.zip" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.zip }}
                        </p>
                    </div>

                    <!-- Country -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Country <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <input
                            v-model="form.country"
                            type="text"
                            maxlength="2"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none uppercase',
                form.errors.country
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                            placeholder="US"
                            required
                        />
                        <p v-if="form.errors.country" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.country }}
                        </p>
                    </div>

                    <!-- Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Location Type <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <select
                            v-model="form.type"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none',
                form.errors.type
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                            required
                        >
                            <option value="pickup">Pickup</option>
                            <option value="distribution_center">Distribution Center</option>
                            <option value="recycling">Recycling</option>
                        </select>
                        <p v-if="form.errors.type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <!-- Conditional: Single Recycling Location Dropdown -->
                    <div v-if="form.type === 'distribution_center'" class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Recycling Location (optional)
                        </label>
                        <select
                            v-model="form.recycling_location_id"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none appearance-none',
                form.errors.recycling_location_id
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                        >
                            <option :value="null">None</option>
                            <option v-for="loc in availableRecyclingLocations" :key="loc.id" :value="loc.id">
                                {{ loc.short_code }} - {{ loc.name || 'Unnamed' }}
                            </option>
                        </select>
                        <p v-if="form.errors.recycling_location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.recycling_location_id }}
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
                Active Location
              </span>
                        </label>
                    </div>
                </div>

                <!-- Submit & Cancel -->
                <div class="flex justify-end space-x-4 mt-8">
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
