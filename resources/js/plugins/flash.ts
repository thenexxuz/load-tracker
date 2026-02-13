import { App, watch } from 'vue'
import Swal from 'sweetalert2'

export default {
    install: (app: App) => {
        // Watch for flash changes on every page load/visit
        // app.config.globalProperties.$watchFlash = (props: any) => {
        //     watch(
        //         () => props.initialPage?.props?.flash,
        //         (flash) => {
        //             if (flash?.success) {
        //                 Swal.fire({
        //                     icon: 'success',
        //                     title: 'Success!',
        //                     text: flash.success,
        //                     timer: 3000,
        //                     showConfirmButton: false,
        //                     toast: true,
        //                     position: 'top-end',
        //                 })
        //             }

        //             if (flash?.error) {
        //                 Swal.fire({
        //                     icon: 'error',
        //                     title: 'Error',
        //                     text: flash.error,
        //                     timer: 5000,
        //                     showConfirmButton: false,
        //                     toast: true,
        //                     position: 'top-end',
        //                 })
        //             }
        //         },
        //         { deep: true }
        //     )
        // }
    }
}