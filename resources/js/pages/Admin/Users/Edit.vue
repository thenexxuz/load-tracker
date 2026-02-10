<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import AdminLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
  user: {
    id: number
    name: string
    email: string
    roles: { name: string }[]
    carrier_id: number | null
  }
  allRoles: string[]
  allCarriers: Array<{
    id: number
    short_code: string
    name: string
  }>
}>()

const form = useForm({
  roles: [] as string[],
  carrier_id: props.user.carrier_id ?? null,
})

// Sync initial roles from props
watch(
  () => props.user,
  (newUser) => {
    if (newUser?.roles) {
      form.roles = newUser.roles.map(r => r.name)
    }
  },
  { immediate: true }
)

// Computed: check if "carrier" role is selected
const hasCarrierRole = computed(() => form.roles.includes('carrier'))

// Clear carrier_id when "carrier" role is removed
watch(hasCarrierRole, (hasRole) => {
  if (!hasRole) {
    form.carrier_id = null
  }
})

const submit = () => {
  // Ensure carrier_id is null if role is not selected
  if (!hasCarrierRole.value) {
    form.carrier_id = null
  }

  form.put(route('admin.users.update', props.user.id), {
    onSuccess: () => {
      // Optional: success toast or redirect
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
  <Head title="Edit User Roles" />

  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
        Edit User: {{ user.name }}
      </h1>

      <!-- Success message -->
      <div
        v-if="form.isSuccessful"
        class="mb-6 p-4 bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200 rounded-lg"
      >
        Roles & Carrier updated successfully!
      </div>

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
        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg dark:shadow-gray-900/30 border border-gray-200 dark:border-gray-700"
      >
        <!-- Roles Section -->
        <div class="mb-8">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Assign Roles to {{ user.name }} ({{ user.email }})
          </label>

          <p v-if="form.errors.roles" class="mb-4 text-sm text-red-600 dark:text-red-400">
            {{ form.errors.roles }}
          </p>

          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <label
              v-for="role in allRoles"
              :key="role"
              class="flex items-center space-x-3 cursor-pointer group"
            >
              <input
                type="checkbox"
                :value="role"
                v-model="form.roles"
                class="h-5 w-5 text-green-600 focus:ring-green-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700"
              />
              <span class="text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-gray-100 transition-colors">
                {{ role }}
              </span>
            </label>
          </div>
        </div>

        <!-- Carrier Assignment – only shown if "carrier" role is selected -->
        <div v-if="hasCarrierRole" class="mb-8">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Assigned Carrier
          </label>

          <p v-if="form.errors.carrier_id" class="mb-4 text-sm text-red-600 dark:text-red-400">
            {{ form.errors.carrier_id }}
          </p>

          <select
            v-model="form.carrier_id"
            class="w-full p-3 border rounded-md focus:ring-2 focus:outline-none border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-blue-500"
          >
            <option :value="null">— None —</option>
            <option v-for="carrier in allCarriers" :key="carrier.id" :value="carrier.id">
              {{ carrier.short_code }} - {{ carrier.name }}
            </option>
          </select>
        </div>

        <!-- Submit -->
        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="form.processing"
            class="bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600 text-white px-6 py-3 rounded-md font-medium transition-colors disabled:opacity-50"
          >
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
