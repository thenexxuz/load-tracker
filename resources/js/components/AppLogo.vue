<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

import { usePage } from '@inertiajs/vue3'

const page = usePage()
const appName = page.props.name || import.meta.env.VITE_APP_NAME || 'Laravel Starter Kit'
const appLogo = page.props.app_logo ?? null
</script>

<template>
    <div v-if="appLogo" class="flex items-center">
        <img :src="appLogo" alt="Logo" class="size-8 rounded-md object-cover" />
    </div>
    <div v-else class="flex aspect-square size-8 items-center justify-center rounded-md">
        <AppLogoIcon class="size-9 fill-current text-[var(--background)] dark:text-white" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            {{ appName }}
        </span>
    </div>
</template>
