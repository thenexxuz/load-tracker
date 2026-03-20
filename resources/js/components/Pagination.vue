<script setup lang="ts">
import { computed } from 'vue'

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface PaginationData {
  current_page: number
  last_page: number
  from: number
  to: number
  total: number
  per_page: number
  links: PaginationLink[]
}

interface Props {
  pagination: PaginationData
  onPageChange?: (url: string) => void
  onPerPageChange?: (perPage: number) => void
  showPerPageSelector?: boolean
  perPageOptions?: number[]
}

const props = withDefaults(defineProps<Props>(), {
  showPerPageSelector: true,
  perPageOptions: () => [10, 15, 20, 25],
})

const emit = defineEmits<{
  pageChange: [url: string]
  perPageChange: [perPage: number]
}>()

const handlePageChange = (url: string | null) => {
  if (url) {
    props.onPageChange?.(url)
    emit('pageChange', url)
  }
}

const handlePerPageChange = (event: Event) => {
  const value = parseInt((event.target as HTMLSelectElement).value)
  props.onPerPageChange?.(value)
  emit('perPageChange', value)
}

const pageNumbers = computed(() => {
  return props.pagination.links.filter(
    (link) => link.label !== 'Previous' && link.label !== 'Next'
  )
})
</script>

<template>
  <div
    v-if="pagination.total > 0"
    class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-b-lg"
  >
    <!-- Info section -->
    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
      Showing {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} of {{ pagination.total }} entries
    </div>

    <!-- Controls section -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
      <!-- Per page selector -->
      <div v-if="showPerPageSelector" class="flex items-center gap-2">
        <label class="text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
          Items per page:
        </label>
        <select
          :value="pagination.per_page"
          @change="handlePerPageChange"
          class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
        >
          <option v-for="option in perPageOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
      </div>

      <!-- Pagination buttons -->
      <div class="flex flex-wrap items-center gap-1 sm:gap-2">
        <template v-for="(link, index) in pageNumbers" :key="index">
          <button
            :disabled="!link.url"
            @click="handlePageChange(link.url)"
            class="px-3 py-2 rounded-md text-sm font-medium transition-colors"
            :class="{
              'bg-blue-600 text-white': link.active,
              'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700':
                !link.active && link.url,
              'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed': !link.url,
            }"
            v-html="link.label"
          />
        </template>
      </div>
    </div>
  </div>
</template>
