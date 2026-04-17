<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import AppSettingsController from '@/actions/App/Http/Controllers/Settings/AppSettingsController';
import { edit } from '@/actions/App/Http/Controllers/Settings/AppSettingsController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    settings: {
        google_sheet_url: string | null;
        app_name: string | null;
        app_logo: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'App settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="App settings" />

        <h1 class="sr-only">App Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Application settings"
                    description="Manage shared configuration used across the application"
                />

                <Form
                    v-bind="AppSettingsController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="app_name">Site name</Label>
                        <Input
                            id="app_name"
                            name="app_name"
                            type="text"
                            class="mt-1 block w-full"
                            :default-value="props.settings.app_name ?? ''"
                            placeholder="Site name"
                        />
                        <InputError :message="errors.app_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="app_logo">App logo</Label>
                        <input id="app_logo" name="app_logo" type="file" class="mt-1" />
                        <p class="text-sm text-muted-foreground">Upload a small square logo (PNG, JPG). Max 2MB.</p>
                        <InputError :message="errors.app_logo" />

                        <div v-if="props.settings.app_logo" class="mt-2">
                            <img :src="props.settings.app_logo" alt="App logo" class="size-16 rounded" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="google_sheet_url">Google Sheets URL</Label>
                        <Input
                            id="google_sheet_url"
                            name="google_sheet_url"
                            type="url"
                            class="mt-1 block w-full"
                            :default-value="props.settings.google_sheet_url ?? ''"
                            placeholder="https://docs.google.com/spreadsheets/d/..."
                        />
                        <p class="text-sm text-muted-foreground">
                            This URL is used as the default source for shipment imports from Google Sheets.
                        </p>
                        <InputError :message="errors.google_sheet_url" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing">Save settings</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>