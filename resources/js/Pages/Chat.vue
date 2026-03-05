<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import ChatRooms from '@/Components/Chat/ChatRooms.vue';
import Conversaton from '@/Components/Chat/Conversaton.vue';
import Users from '@/Components/Chat/Users.vue';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const users = ref([]);

const requesterId = computed(() => page.props.auth?.user?.id ?? null);

const loadUsers = async () => {
    const response = await axios.get('/api/users');
    const apiUsers = response.data?.data?.users ?? [];

    users.value = apiUsers.filter((user) => {
        return user.id !== requesterId.value;
    });
};

onMounted(() => {
    loadUsers();

    if (window.Echo) {
        window.Echo.private('users.status').listen('.user.status.changed', (event) => {
            users.value = users.value.map((user) => {
                if (user.id !== event.user_id) {
                    return user;
                }

                return {
                    ...user,
                    is_online: Boolean(event.is_online),
                };
            });
        });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('users.status');
    }
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