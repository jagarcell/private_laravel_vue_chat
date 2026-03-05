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
const requestStates = ref({});
const incomingRequest = ref(null);

const requesterId = computed(() => page.props.auth?.user?.id ?? null);

const loadUsers = async () => {
    const response = await axios.get('/api/users');
    const apiUsers = response.data?.data?.users ?? [];

    users.value = apiUsers.filter((user) => {
        return user.id !== requesterId.value;
    });
};

const setRequestState = (userId, state) => {
    requestStates.value = {
        ...requestStates.value,
        [userId]: state,
    };
};

const sendChatRequest = async (user) => {
    await axios.post('/api/chat-requests', {
        to_user_id: user.id,
    });

    setRequestState(user.id, 'pending');
};

const acceptChatRequest = async (request) => {
    await axios.post('/api/chat-requests/respond', {
        requester_user_id: request.from_user_id,
        action: 'accept',
    });

    setRequestState(request.from_user_id, 'connected');
    incomingRequest.value = null;
};

const declineChatRequest = async (request) => {
    await axios.post('/api/chat-requests/respond', {
        requester_user_id: request.from_user_id,
        action: 'decline',
    });

    incomingRequest.value = null;
};

const dismissDeclined = (user) => {
    setRequestState(user.id, 'none');
};

const closeChat = async (user) => {
    await axios.post('/api/chat-requests/close', {
        to_user_id: user.id,
    });

    setRequestState(user.id, 'none');
};

const handleChatRequestMessage = (event) => {
    const fromUserId = Number(event?.from_user_id ?? 0);
    const type = String(event?.type ?? '');

    if (type === 'requested') {
        incomingRequest.value = {
            from_user_id: fromUserId,
            from_user_name: String(event?.from_user_name ?? 'User'),
        };

        return;
    }

    if (type === 'accepted') {
        setRequestState(fromUserId, 'connected');

        return;
    }

    if (type === 'declined') {
        setRequestState(fromUserId, 'declined');

        return;
    }

    if (type === 'closed') {
        setRequestState(fromUserId, 'closed');
    }
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

        if (requesterId.value) {
            window.Echo.private(`users.chat-request.${requesterId.value}`)
                .listen('.chat.request.message', handleChatRequestMessage);
        }
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.private('users.status').stopListening('.user.status.changed');

        if (requesterId.value) {
            window.Echo.private(`users.chat-request.${requesterId.value}`)
                .stopListening('.chat.request.message', handleChatRequestMessage);
        }
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
                            <Users
                                :users="users"
                                :request-states="requestStates"
                                @request-chat="sendChatRequest"
                                @dismiss-declined="dismissDeclined"
                                @close-chat="closeChat"
                            />
                            <ChatRooms />
                        </div>
                    </aside>

                    <section class="lg:col-span-8">
                        <Conversaton
                            :incoming-request="incomingRequest"
                            @accept-request="acceptChatRequest"
                            @decline-request="declineChatRequest"
                        />
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>