<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ChatRooms from '@/Components/Chat/ChatRooms.vue';
import Conversaton from '@/Components/Chat/Conversaton.vue';
import Users from '@/Components/Chat/Users.vue';

defineProps({
    users: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Chat" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Chat
            </h2>
        </template>

        <div class="p-6">
            <div class="mx-auto max-w-7xl">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <aside class="lg:col-span-4">
                        <div class="grid gap-4">
                            <Users :users="users" />
                            <ChatRooms />
                        </div>
                    </aside>

                    <section class="lg:col-span-8">
                        <Conversaton />
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>