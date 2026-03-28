<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { Notify } from 'notiflix'
import { onMounted, onUnmounted } from 'vue'

import NotificationToast from '@/components/NotificationToast.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    APP_DEBUG?: string; // from .env, as a string
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

  if (userId) {
    const channelName = `user.${userId}`;
    console.log(`[Echo] Subscribing to ${channelName}`);

    if (import.meta.env.VITE_APP_DEBUG === 'true') {
      console.debug(`[Echo] Debug mode is ON — will log incoming notifications to console`)
      Notify.info(`Subscribed to notifications on ${channelName}`, {
        timeout: 3000,
        clickToClose: true,
        position: 'right-top',
      });
    }

    window.Echo.channel(channelName)  // or .private(channelName) if using PrivateChannel
      .listen('NewNotification', (event) => {
        console.log('[Echo] New notification:', event);

        Notify.info(event.message || 'New notification', {
          title: event.title || 'Notification',
          timeout: 8000,
          clickToClose: true,
          position: 'right-top',
          onClick: () => {
            if (event.link) router.visit(event.link);
          }
        });
      })
      .error((err) => console.error('[Echo] Channel error:', err));
  } else {
    console.warn('[Echo] No user ID found in props — cannot subscribe to channel')
  }
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
