import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, onMounted, onUnmounted } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import FlashPlugin from './plugins/flash';

import { initializeTheme } from './composables/useAppearance';
import { configureEcho, echo } from '@laravel/echo-vue';

// ──────────────────────────────────────────────────────────────
// Configure Echo / Reverb
// ──────────────────────────────────────────────────────────────
configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
    },
});

window.Echo = echo();

// ──────────────────────────────────────────────────────────────
// Join user channel when app mounts
// ──────────────────────────────────────────────────────────────
onMounted(() => {
    const userIdMeta = document.querySelector('meta[name="user-id"]');
    const userId = userIdMeta?.getAttribute('content');

    if (!userId) {
        console.warn('No user ID found in meta[name="user-id"] — skipping channel join');
        return;
    }

    console.log(`Joining private channel: user.${userId}`);

    window.Echo.private(`user.${userId}`)
        .listen('NewNotification', (event) => {
            console.log('New notification received:', event);
            // Here you can show Notiflix toast, update UI, etc.
        })
        .here((users) => {
            console.log('Users currently in channel:', users);
        })
        .joining((user) => {
            console.log('User joined:', user);
        })
        .leaving((user) => {
            console.log('User left:', user);
        })
        .error((err) => {
            console.error('Error in private-user channel:', err);
        });
});

onUnmounted(() => {
    const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
    if (userId) {
        window.Echo.leave(`user.${userId}`);
        console.log(`Left channel: user.${userId}`);
    }
});

// ──────────────────────────────────────────────────────────────
// Inertia App Creation
// ──────────────────────────────────────────────────────────────
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(FlashPlugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Apply theme on load
initializeTheme();