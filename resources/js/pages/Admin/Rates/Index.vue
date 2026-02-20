<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AppLayout.vue'
import { Confirm, Notify } from 'notiflix';
import { onMounted } from 'vue';

const page = usePage()

const props = defineProps<{
  rates: {
    data: Array<{
      id: number
      carrier: { name: string; short_code: string } | null
      pickup_location: { short_code: string; name: string | null } | null
      dc_location: { short_code: string; name: string | null } | null
      rate: number
    }>
    current_page: number
    last_page: number
    from: number
    to: number
    total: number
  }
}>()

const destroy = async (id: number) => {
  const result = await Confirm.show(
    'Delete Rate',
    'Are you sure you want to delete this rate? This action cannot be undone.',
    'Yes, delete it',
    'Cancel',
    () => {
      router.delete(route('admin.rates.destroy', id), {
        onSuccess: () => {
          Notify.success('Rate has been deleted.')
        },
        onError: () => {
          Notify.failure('Failed to delete rate.')
        }
      })
    },
    () => {
      // Cancelled - do nothing
    },
    {
      titleColor: '#ff0000',
      okButtonBackground: '#ff0000',
    }
  )
}

const goToShow = (id: number) => {
  router.visit(route('admin.rates.show', id))
}

onMounted(() => {
  if (page.props.flash?.success) {
    Notify.success(page.props.flash.success)
  }
  if (page.props.flash?.error) {
    Notify.failure(page.props.flash.error)
  }
  if (page.props.flash?.info) {
    Notify.info(page.props.flash.info)
  }
  if (page.props.flash?.warning) {
    Notify.warning(page.props.flash.warning)
  }
})
</script>

<template>
  <Head title="Manage Rates" />

  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Rates Management
        </h1>
        <a
          :href="route('admin.rates.create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-md font-medium transition-colors"
        >
          Add New Rate
        </a>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md dark:shadow-gray-900/30">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-left">
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Carrier</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Pickup Location</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">DC Location</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">Rate</th>
              <th class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="rate in rates.data"
              :key="rate.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
              @click="goToShow(rate.id)"
            >
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                <div v-if="rate.has_notes" 
                    class="absolute top-1 left-1 text-blue-500 opacity-70"
                    title="Has notes">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                  </svg>
                </div>
                {{ rate.carrier?.name || '—' }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                {{ rate.pickup_location?.short_code || '—' }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                {{ rate.dc_location?.short_code || '—' }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                ${{ rate.rate.toFixed(2) }}
              </td>
              <td class="px-6 py-4 text-center space-x-5" @click.stop>
                <a
                  :href="route('admin.rates.edit', rate.id)"
                  class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                  title="Edit Rate"
                  @click.stop
                >
                  <svg class="w-5.5 h-5.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </a>

                <button
                  @click.stop="destroy(rate.id)"
                  class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                  title="Delete Rate"
                >
                  <svg class="w-5.5 h-5.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                  </svg>
                </button>
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="!rates.data?.length">
              <td colspan="5" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400 text-lg font-medium">
                No rates found
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="rates.data?.length" class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-gray-700 dark:text-gray-300">
          <div>
            Showing {{ rates.from || 0 }} to {{ rates.to || 0 }} of {{ rates.total || 0 }} rates
          </div>

          <div class="flex items-center space-x-2">
            <button
              :disabled="rates.current_page === 1"
              @click="router.get(route('admin.rates.index', { page: rates.current_page - 1 }), {}, { preserveState: true, preserveScroll: true })"
              class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Previous
            </button>

            <span class="px-4 py-2 font-medium">
              Page {{ rates.current_page }} of {{ rates.last_page }}
            </span>

            <button
              :disabled="rates.current_page === rates.last_page"
              @click="router.get(route('admin.rates.index', { page: rates.current_page + 1 }), {}, { preserveState: true, preserveScroll: true })"
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
