import '../css/app.css';
import 'toastr/build/toastr.min.css'
import 'sweetalert2/dist/sweetalert2.min.css'

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import Swal from 'sweetalert2'
import toastr from 'toastr'
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js'

import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: 'toast-top-right',
    preventDuplicates: true,
    newestOnTop: true,
    showMethod: 'fadeIn',
    hideMethod: 'fadeOut',
    timeOut: 5000,
    extendedTimeOut: 1000,
    tapToDismiss: true,
}

window.toastr = toastr

Swal.mixin({
    customClass: {
        confirmButton: 'btn btn-danger mx-2',
        cancelButton: 'btn btn-secondary mx-2'
    },
    buttonsStyling: false,
    reverseButtons: true,
    showCloseButton: true,
    focusConfirm: false,
})

export const successToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    icon: 'success',
    iconColor: '#10b981',
    customClass: {
        popup: 'bg-green-50 dark:bg-green-900/30 text-green-900 dark:text-green-100',
    },
})

export const errorToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 5000,
    icon: 'error',
    iconColor: '#ef4444',
})

window.Swal = Swal
window.successToast = successToast
window.errorToast = errorToast

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
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
