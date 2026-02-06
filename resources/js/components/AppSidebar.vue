<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
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
  Ruler,
  Locate,
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

const collapsed = ref(false)

// Check if current page is any location-related route
const isOnLocations = computed(() => {
  return route().current('admin.locations.*')
})

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
  ...(hasLocationsAccess && isOnLocations.value
    ? [{
        title: 'Calculate Multi-Location Route',
        href: route('admin.locations.multi-route'),
        icon: Locate,
      }]
    : []),
  ...(hasLocationsAccess && isOnLocations.value
    ? [{
        title: 'Recycling Distance',
        href: route('admin.locations.recycling-distances'),
        icon: Ruler,
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
              'justify-start space-x-3': !collapsed
            }"
          >
            <Link :href="item.href" class="flex items-center">
              <div class="flex-shrink-0 flex items-center justify-center w-6 h-6">
                <component :is="item.icon" class="w-5 h-5" />
              </div>
              <span
                v-show="!collapsed"
                class="ml-3 truncate text-base font-medium"
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
/* Ensure perfect centering when collapsed */
.sidebar-collapsed .sidebar-menu-button {
  padding-left: 0 !important;
  padding-right: 0 !important;
  justify-content: center !important;
}

.sidebar-collapsed .sidebar-menu-button svg {
  margin: 0 auto;
}
</style>
