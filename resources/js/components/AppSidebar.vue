<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { BookOpen, Folder, LayoutGrid, SettingsIcon, Truck, User } from 'lucide-vue-next';

import NavFooter from '@/components/NavFooter.vue'
import NavMain from '@/components/NavMain.vue'
import NavUser from '@/components/NavUser.vue'
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar'
import { configure, dashboard } from '@/routes'
import { type NavItem } from '@/types'

import AppLogo from './AppLogo.vue'

// Get current user from Inertia shared props
const { auth } = usePage().props
const userRoles = auth?.user?.roles || [] // array of role names (from Spatie)

const hasUserAccess = userRoles.includes('administrator')
const hasCarrierAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(hasUserAccess
        ? [
            {
                title: 'User Management',
                href: route('admin.users.index'),
                icon: User,
            },
        ]
        : []),
    ...(hasCarrierAccess
        ? [
            {
                title: 'Carrier Management',
                href: route('admin.carriers.index'),
                icon: Truck,
            },
        ]
        : []),
]

const footerNavItems: NavItem[] = []
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
