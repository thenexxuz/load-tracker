<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { type NavItem } from '@/types'

defineProps<{
    items: NavItem[]
}>()

const page = usePage()
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem v-for="item in items" :key="item.title">
            <SidebarMenuButton
                as-child
                :class="[
          'rounded-lg transition-colors',
          page.url.startsWith(item.href)
            ? 'bg-accent text-accent-foreground font-medium shadow-sm'
            : 'text-muted-foreground hover:bg-accent/70 hover:text-accent-foreground'
        ]"
            >
                <Link
                    :href="item.href"
                    class="flex items-center justify-center w-full h-full"
                >
                    <component :is="item.icon" class="w-5.5 h-5.5 shrink-0" />
                    <span class="ml-1 text-base font-medium data-[state=collapsed]:hidden">
            {{ item.title }}
          </span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
