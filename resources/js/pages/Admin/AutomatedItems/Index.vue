<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Confirm, Notify } from 'notiflix'
import { onMounted } from 'vue'
import { route } from 'ziggy-js'

import ActionIconButton from '@/components/ActionIconButton.vue'
import Pagination from '@/components/Pagination.vue'
import AdminLayout from '@/layouts/AppLayout.vue'

const page = usePage()

const props = defineProps<{
  automatedItems: {
    data: Array<{
      id: number
      name: string
      monitorable_label: string
      monitored_fields: string[]
      role_name: string
      is_active: boolean
    }>
    current_page: number
    last_page: number
    from: number
    to: number
    total: number
    per_page: number
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
}>()

const destroy = async (id: number) => {
  await Confirm.show(
    'Delete Automated Item',
    'Are you sure you want to delete this automated item?',
    'Yes, delete it',
    'Cancel',
    () => {
      router.delete(route('admin.automated-items.destroy', id), {
        onSuccess: () => {
          Notify.success('Automated item has been deleted.')
        },
        onError: () => {
          Notify.failure('Failed to delete automated item.')
        },
      })
    },
    () => {},
    {
      titleColor: '#ff0000',
      okButtonBackground: '#ff0000',
    }
  )
}

const changePage = (url: string) => {
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  })
}

const changePerPage = (value: number) => {
  router.get(
    route('admin.automated-items.index'),
    { per_page: value, page: 1 },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

onMounted(() => {
  if (page.props.flash?.success) Notify.success(page.props.flash.success)
  if (page.props.flash?.error) Notify.failure(page.props.flash.error)
})
</script>

<template>
  <Head title="Automated Items" />

  <AdminLayout>
    <div class="p-6">
      <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Automated Items
        </h1>
        <Link
          :href="route('admin.automated-items.create')"
          class="rounded-md bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
        >
          Create New Item
        </Link>
      </div>

      <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Name
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Model
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Properties
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Notify Role
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Status
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="props.automatedItems.data.length === 0">
                <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                  No automated items found.
                </td>
              </tr>
              <tr
                v-for="item in props.automatedItems.data"
                :key="item.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
              >
                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ item.name }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                  {{ item.monitorable_label }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                  <div class="flex flex-wrap gap-2">
                    <span
                      v-for="field in item.monitored_fields"
                      :key="field"
                      class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                    >
                      {{ field }}
                    </span>
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                  {{ item.role_name }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm">
                  <span
                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="item.is_active
                      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                      : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                  >
                    {{ item.is_active ? 'Active' : 'Paused' }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-4">
                  <ActionIconButton
                    action="edit"
                    :href="route('admin.automated-items.edit', item.id)"
                    title="Edit Automated Item"
                  />
                  <ActionIconButton
                    action="delete"
                    title="Delete Automated Item"
                    @click="destroy(item.id)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <Pagination
        :pagination="props.automatedItems"
        @pageChange="changePage"
        @perPageChange="changePerPage"
      />
    </div>
  </AdminLayout>
</template>
