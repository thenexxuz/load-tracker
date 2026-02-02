<script setup lang="ts">
import { ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  BookOpen,
  Folder,
  LayoutGrid,
  Map,
  SearchIcon,
  Truck,
  TruckIcon,
  User,
  DollarSign,
  AtSign,
  NotepadTextDashed,
} from 'lucide-vue-next'

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
import { dashboard } from '@/routes'
import { type NavItem } from '@/types'

import AppLogo from './AppLogo.vue'

const { auth } = usePage().props
const userRoles = auth?.user?.roles || []

const hasUserAccess = userRoles.includes('administrator')
const hasAuditAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasRatesAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasCarrierAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasTemplateAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasLocationsAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')
const hasShipmentAccess = userRoles.includes('administrator') || userRoles.includes('supervisor') || userRoles.includes('truckload') || userRoles.includes('data-entry')

const collapsed = ref(false) // Track collapsed state

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
  ...(hasTemplateAccess
    ? [{
        title: 'Template Management',
        href: route('admin.templates.index'),
        icon: NotepadTextDashed,
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
  <Sidebar collapsible="icon" variant="inset" v-model:collapsed="collapsed">
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
          <SidebarMenuButton
            size="lg"
            as-child
            :class="{
              'justify-center': collapsed,
              'justify-start space-x-6': !collapsed
            }"
          >
            <Link :href="item.href" class="flex aspect-square size-8 items-center justify-center rounded-md">
              <!-- Centered icon wrapper -->
              <div class="p-0.5 ps-1.5 flex items-center justify-center">
                <component :is="item.icon" class="w-5 h-5" />
              </div>

              <!-- Text hidden when collapsed -->
              <span
                v-show="!collapsed"
                class="mb-0.5 truncate leading-tight font-semibold"
              >
                {{ item.title }}
              </span>
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

<style scoped>
.sidebar-collapsed .sidebar-menu-button {
  padding-left: 0 !important;
  padding-right: 0 !important;
}

.sidebar-collapsed .sidebar-menu-button svg {
  margin: 0 auto;
}
</style>
