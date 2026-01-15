<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    user: Object,
    allRoles: Array
})

const form = useForm({
    roles: props.user.roles.map(r => r.name)
})

const submit = () => {
    form.put(route('admin.users.update', props.user.id))
}
</script>

<template>
    <div>
        <h2>Assign Roles to {{ user.name }}</h2>

        <form @submit.prevent="submit">
            <div class="space-y-2">
                <label v-for="role in allRoles" :key="role">
                    <input type="checkbox" v-model="form.roles" :value="role" />
                    {{ role }}
                </label>
            </div>

            <button type="submit" class="mt-4 bg-green-600 text-white px-4 py-2">
                Save Roles
            </button>
        </form>
    </div>
</template>
