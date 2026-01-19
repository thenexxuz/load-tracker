<!-- resources/js/Components/ConfirmationModal.vue -->
<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
    title?: string
    message: string
    confirmText?: string
    cancelText?: string
}>()

defineEmits(['confirm', 'cancel'])

const show = ref(false)

const open = () => (show.value = true)
const close = () => (show.value = false)

defineExpose({ open })
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                {{ title || 'Confirm Action' }}
            </h3>
            <p class="text-gray-700 dark:text-gray-300 mb-6">
                {{ message }}
            </p>
            <div class="flex justify-end space-x-3">
                <button
                    @click="close"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                >
                    {{ cancelText || 'Cancel' }}
                </button>
                <button
                    @click="$emit('confirm'); close()"
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
                >
                    {{ confirmText || 'Delete' }}
                </button>
            </div>
        </div>
    </div>
</template>
