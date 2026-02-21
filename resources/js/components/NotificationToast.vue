<script setup>
import { ref, onMounted } from 'vue'
import { Notify } from 'notiflix/build/notiflix-notify-aio'
import { usePage, router } from '@inertiajs/vue3'

const notifications = ref([])

onMounted(() => {
    const userId = usePage().props.auth.user.id

    window.Echo.private(`user.${userId}`)
        .listen('NewNotification', (e) => {
            notifications.value.push(e)

            // Show popup with Notiflix
            Notify.info(e.message, {
                timeout: 8000,
                clickToClose: true,
                position: 'right-top',
                // optional: add link click handler
                onClick: () => {
                    if (e.link) router.visit(e.link)
                }
            })
        })
})
</script>

<template>
    <!-- This component is just for listening to notifications and showing toasts -->
</template>