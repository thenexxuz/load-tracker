import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, onUnmounted } from 'vue';
import { ZiggyVue } from 'ziggy-js'
import FlashPlugin from './plugins/flash';

import { initializeTheme } from './composables/useAppearance';
import { configureEcho, echo } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
    host: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    port: import.meta.env.VITE_REVERB_PORT ?? 6001,
    key: import.meta.env.VITE_REVERB_KEY ?? 'local',
    cluster: import.meta.env.VITE_REVERB_CLUSTER ?? 'mt1',
    encrypted: true,
});

window.Echo = echo();

window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('Reverb connected successfully');
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    console.warn('Reverb disconnected');
});

window.Echo.connector.pusher.connection.bind('failed', () => {
    console.error('Reverb connection failed');
});

const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
if (userId) {
    window.Echo.join(`user.${userId}`)
        .here((users) => {
            console.log('Current users in channel:', users);
        })
        .joining((user) => {
            console.log('User joined:', user);
        })
        .leaving((user) => {
            console.log('User left:', user);
        })
        .error((err) => {
            console.error('Error joining channel:', err);
        });
} else {
    console.warn('No user ID found in meta tags, skipping Echo channel join');
}

// Optional: Global error handling for Echo connection issues

window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.error('Reverb connection error:', err)
});

window.Echo.connector.pusher.connection.bind('state_change', (states) => {
    console.log('Reverb connection state changed:', states);
});

window.Echo.connector.pusher.connection.bind('ping', () => {
    console.log('Reverb connection ping');
});

window.Echo.connector.pusher.connection.bind('pong', () => {
    console.log('Reverb connection pong');
});

window.Echo.connector.pusher.connection.bind('connecting_in', (delay) => {
    console.log(`Reverb will attempt to reconnect in ${delay} seconds`);
});

window.Echo.connector.pusher.connection.bind('reconnecting', (delay) => {
    console.log(`Reverb is reconnecting, attempt #${delay}`);
});

window.Echo.connector.pusher.connection.bind('reconnected', () => {
    console.log('Reverb reconnected successfully');
});

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

// This will set light / dark mode on page load...
initializeTheme();

onUnmounted(() => {
    window.Echo.leave(`user.${userId}`)
})
