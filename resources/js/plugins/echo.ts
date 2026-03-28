// resources/js/plugins/echo.ts
import { configureEcho, echo } from '@laravel/echo-vue'
import type { App } from 'vue'

export default {
    install: (app: App) => {
        // ──────────────────────────────────────────────────────────────
        // Configure Echo / Reverb once on app boot
        // ──────────────────────────────────────────────────────────────
        configureEcho({
            broadcaster: 'pusher',
            key: import.meta.env.VITE_PUSHER_APP_KEY,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
            forceTLS: true,
            encrypted: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            },
        });

        // Attach global Echo instance
        const echoInstance = echo()
        app.config.globalProperties.$echo = echoInstance
        window.Echo = echoInstance // keep global access if needed

        console.log('[Echo Plugin] Echo initialized')
    }
}