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
const selectedUserId = ref(null);
const messageHistories = ref({});

const requesterId = computed(() => page.props.auth?.user?.id ?? null);
const selectedUser = computed(() => {
    return users.value.find((user) => user.id === selectedUserId.value) ?? null;
});

const selectedUserMessages = computed(() => {
    if (!selectedUser.value) {
        return [];
    }

    return messageHistories.value[selectedUser.value.id] ?? [];
});

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

const selectUser = (user) => {
    selectedUserId.value = user?.id ?? null;
};

const closeChat = async (user) => {
    await axios.post('/api/chat-requests/close', {
        to_user_id: user.id,
    });

    setRequestState(user.id, 'none');
};

const appendMessageToHistory = (userId, text) => {
    const existing = messageHistories.value[userId] ?? [];

    messageHistories.value = {
        ...messageHistories.value,
        [userId]: [...existing, text],
    };
};

const sendMessage = async (content) => {
    const targetUser = selectedUser.value;

    if (!targetUser || !targetUser.is_online) {
        return;
    }

    await axios.post('/api/chat-message/send', {
        to_user_id: targetUser.id,
        message: content,
    });

    appendMessageToHistory(targetUser.id, `You: ${content}`);
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

const handleChatMessageSent = (event) => {
    const fromUserId = Number(event?.from_user_id ?? 0);
    const fromUserName = String(event?.from_user_name ?? 'User');
    const message = String(event?.message ?? '').trim();

    if (!fromUserId || message.length === 0) {
        return;
    }

    appendMessageToHistory(fromUserId, `${fromUserName}: ${message}`);
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

            window.Echo.private(`users.chat-message.${requesterId.value}`)
                .listen('.chat.message.sent', handleChatMessageSent);
        }
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.private('users.status').stopListening('.user.status.changed');

        if (requesterId.value) {
            window.Echo.private(`users.chat-request.${requesterId.value}`)
                .stopListening('.chat.request.message', handleChatRequestMessage);

            window.Echo.private(`users.chat-message.${requesterId.value}`)
                .stopListening('.chat.message.sent', handleChatMessageSent);
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
                                :selected-user-id="selectedUserId"
                                @request-chat="sendChatRequest"
                                @dismiss-declined="dismissDeclined"
                                @close-chat="closeChat"
                                @select-user="selectUser"
                            />
                            <ChatRooms />
                        </div>
                    </aside>

                    <section class="lg:col-span-8">
                        <Conversaton
                            :incoming-request="incomingRequest"
                            :selected-user="selectedUser"
                            :message-history="selectedUserMessages"
                            @accept-request="acceptChatRequest"
                            @decline-request="declineChatRequest"
                            @send-message="sendMessage"
                        />
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>