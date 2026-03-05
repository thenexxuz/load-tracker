<script setup lang="ts">
import NotificationToast from '@/components/NotificationToast.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Notify } from 'notiflix'

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage()
const userId = page.props.auth?.user?.id  // ← safer than meta tag

onMounted(() => {
  if (!userId) {
    console.warn('[Echo] No user ID in props — skipping channel join')
    return
  }

  const channelName = `user.${userId}`
  console.log(`[Echo] Joining channel: ${channelName}`)

  window.Echo.connector.pusher.connection.bind('connected', () => {
    // safe to join private channels now
    window.Echo.private(channelName)
    .listenAny((eventName, event) => {
      console.log('[Echo] New notification received ('+eventName+'):', event)

      Notify.info(event.message || 'New notification', {
        title: event.title || 'Notification',
        timeout: 8000,
        clickToClose: true,
        position: 'right-top',
        onClick: () => {
          if (event.link) router.visit(event.link)
        }
      })
    })
    .error((err) => {
      console.error('[Echo] Channel error:', err)
    })
    .subscribed(() => {
      console.log(`[Echo] Successfully subscribed to ${channelName}`)
    })
});
})

onUnmounted(() => {
  if (userId) {
    window.Echo.leave(`user.${userId}`)
    console.log(`[Echo] Left channel: user.${userId}`)
  }
})
</script>

<template>
    <NotificationToast />
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
