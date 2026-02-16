<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { watch } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'
import { Notify } from 'notiflix';

defineProps<{
    availableRecyclingLocations: Array<{
        id: number
        short_code: string
        name: string | null
    }>
}>()

const form = useForm({
    short_code: '',
    name: '',
    address: '',
    city: '',
    state: '',
    zip: '',
    country: 'US',
    type: 'pickup',
    latitude: null as number | null,
    longitude: null as number | null,
    is_active: true,
    recycling_location_id: null as number | null,
    email: '',
    expected_arrival_time: null,
})

const submit = () => {
    form.post(route('admin.locations.store'), {
        onSuccess: () => {
            form.reset()
            form.type = 'pickup'
        },
        onError: (errors) => {
            console.log('Form errors:', errors)
        },
        onFinish: () => {
            form.processing = false
        },
    })
}

watch(
    () => form.type,
    (newType) => {
        if (newType !== 'distribution_center') {
            form.recycling_location_id = null
        }
    }
)
</script>

<template>
    <Head title="Create Location" />

    <AdminLayout>
        <div class="p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
                Create New Location
            </h1>

            <!-- Error banner -->
            <div
                v-if="Object.keys(form.errors).length"
                class="mb-6 p-4 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg"
            >
                Please fix the errors below.
            </div>

            <!-- Form -->
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
                            placeholder="e.g. Dallas Pickup Hub"
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

                    <div class="col-span-full grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Latitude</label>
                            <input
                            v-model="form.latitude"
                            type="number"
                            step="any"
                            class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
                            placeholder="e.g. 40.7128"
                            />
                            <p v-if="form.errors.latitude" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.latitude }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Longitude</label>
                            <input
                            v-model="form.longitude"
                            type="number"
                            step="any"
                            class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
                            placeholder="e.g. -74.0060"
                            />
                            <p v-if="form.errors.longitude" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.longitude }}
                            </p>
                        </div>
                    </div>

                    <!-- Email (NEW) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Email
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none',
                form.errors.email
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                            placeholder="location@example.com"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Expected Arrival Time (NEW) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Expected Arrival Time
                        </label>
                        <input
                            v-model="form.expected_arrival_time"
                            type="time"
                            :class="[
                'w-full p-3 border rounded-md focus:ring-2 focus:outline-none',
                form.errors.expected_arrival_time
                  ? 'border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-950/30'
                  : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500'
              ]"
                        />
                        <p v-if="form.errors.expected_arrival_time" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.expected_arrival_time }}
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

                    <!-- Recycling Location -->
                    <div v-if="form.type === 'distribution_center'" class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Recycling Location
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

                <!-- Submit -->
                <div class="flex justify-end mt-8">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600 text-white px-8 py-3 rounded-md font-medium transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Location' }}
                    </button>
                </div>
            </form>

            <!-- Back Link -->
            <div class="mt-8 text-center">
                <a href="javascript:history.back()" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
                    ← Back to Locations List
                </a>
            </div>
        </div>
    </AdminLayout>
</template>
