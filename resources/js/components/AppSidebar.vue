<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { BookOpen, Folder, LayoutGrid, Map, SearchIcon, Truck, TruckIcon, User, DollarSign } from 'lucide-vue-next';

import NavFooter from '@/components/NavFooter.vue'
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

const { auth } = usePage().props
const userRoles = auth?.user?.roles || []

const hasUserAccess = userRoles.includes('administrator')
const hasAuditAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasRatesAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasCarrierAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasLocationsAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasShipmentAccess = userRoles.includes('administrator') || userRoles.includes('supervisor') || userRoles.includes('truckload') || userRoles.includes('data-entry')

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(hasAuditAccess
        ? [{
            title: 'Audit History',
            href: route('admin.audits.index'),
            icon: SearchIcon,
        }]
        : []),
    ...(hasUserAccess
        ? [{
            title: 'User Management',
            href: route('admin.users.index'),
            icon: User,
        }]
        : []),
    ...(hasCarrierAccess
        ? [{
            title: 'Carrier Management',
            href: route('admin.carriers.index'),
            icon: Truck,
        }]
        : []),
    ...(hasRatesAccess
        ? [{
            title: 'Rates Management',
            href: route('admin.rates.index'),
            icon: DollarSign,
        }]
        : []),
    ...(hasLocationsAccess
        ? [{
            title: 'Location Management',
            href: route('admin.locations.index'),
            icon: Map,
        }]
        : []),
    ...(hasShipmentAccess
        ? [{
            title: 'Shipments',
            href: route('admin.shipments.index'),
            icon: TruckIcon,
        }]
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
            <SidebarMenu>
                <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="item.href" class="flex items-center gap-5">
                            <component :is="item.icon" class="w-5 h-5" />
                            <span class="text-base font-medium">{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
