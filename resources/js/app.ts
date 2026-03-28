import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, onMounted, onUnmounted } from 'vue';
import { ZiggyVue } from 'ziggy-js';

import { initializeTheme } from './composables/useAppearance';
import EchoPlugin from './plugins/echo';
import FlashPlugin from './plugins/flash';


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
            .use(EchoPlugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Apply theme on load
initializeTheme();