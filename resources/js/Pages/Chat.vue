<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import ChatRooms from '@/Components/Chat/ChatRooms.vue';
import Conversaton from '@/Components/Chat/Conversaton.vue';
import Users from '@/Components/Chat/Users.vue';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const users = ref([]);
const requestStates = ref({});
const incomingRequest = ref(null);
const selectedUserId = ref(null);
const messageHistories = ref({});
const unreadIncomingCounts = ref({});

const requesterId = computed(() => page.props.auth?.user?.id ?? null);
const normalizeUserId = (value) => {
    const normalized = Number(value);

    if (!Number.isInteger(normalized) || normalized <= 0) {
        return null;
    }

    return normalized;
};
const storageKey = computed(() => {
    if (!requesterId.value) {
        return null;
    }

    return `chat.state.${requesterId.value}`;
});
const selectedUser = computed(() => {
    const activeUserId = normalizeUserId(selectedUserId.value);

    return users.value.find((user) => user.id === activeUserId) ?? null;
});

const selectedUserMessages = computed(() => {
    if (!selectedUser.value) {
        return [];
    }

    return messageHistories.value[selectedUser.value.id] ?? [];
});

const selectedUserRequestState = computed(() => {
    if (!selectedUser.value) {
        return 'none';
    }

    return requestStates.value[selectedUser.value.id] ?? 'none';
});

const readPersistedState = () => {
    if (!storageKey.value) {
        return null;
    }

    try {
        const rawState = window.localStorage.getItem(storageKey.value);

        if (!rawState) {
            return null;
        }

        return JSON.parse(rawState);
    } catch {
        return null;
    }
};

const persistState = () => {
    if (!storageKey.value) {
        return;
    }

    const payload = {
        requestStates: requestStates.value,
        selectedUserId: selectedUserId.value,
    };

    window.localStorage.setItem(storageKey.value, JSON.stringify(payload));
};

const restorePersistedState = () => {
    const persistedState = readPersistedState();

    if (!persistedState) {
        return;
    }

    requestStates.value = persistedState.requestStates ?? {};
    selectedUserId.value = normalizeUserId(persistedState.selectedUserId);
};

const sanitizeStateForKnownUsers = () => {
    const validUserIds = new Set(users.value.map((user) => user.id));

    const keepKnownUsersOnly = (stateObject) => {
        return Object.fromEntries(
            Object.entries(stateObject).filter(([userId]) => validUserIds.has(Number(userId))),
        );
    };

    requestStates.value = keepKnownUsersOnly(requestStates.value);
    if (selectedUserId.value && !validUserIds.has(Number(selectedUserId.value))) {
        selectedUserId.value = null;
    }
};

const formatMessageLine = (chatMessage) => {
    if (chatMessage.is_mine) {
        return `You: ${chatMessage.message}`;
    }

    return `${chatMessage.from_user_name ?? 'User'}: ${chatMessage.message}`;
};

const setConversationHistory = (userId, messages) => {
    messageHistories.value = {
        ...messageHistories.value,
        [userId]: messages.map(formatMessageLine),
    };
};

const loadUnreadIncomingCounts = async () => {
    const response = await axios.get('/api/chat-messages/unread-counts');
    const counts = response.data?.data?.counts ?? [];

    unreadIncomingCounts.value = counts.reduce((accumulator, item) => {
        const userId = Number(item?.user_id ?? 0);
        const unreadCount = Number(item?.unread_count ?? 0);

        if (!userId || unreadCount <= 0) {
            return accumulator;
        }

        return {
            ...accumulator,
            [userId]: unreadCount,
        };
    }, {});
};

const loadConversationForUser = async (userId) => {
    if (!userId) {
        return;
    }

    const response = await axios.get(`/api/chat-messages/conversation/${userId}`);
    const messages = response.data?.data?.messages ?? [];

    setConversationHistory(userId, messages);
};

const markConversationAsRead = async (userId) => {
    if (!userId) {
        return;
    }

    await axios.post(`/api/chat-messages/conversation/${userId}/read`);

    unreadIncomingCounts.value = {
        ...unreadIncomingCounts.value,
        [userId]: 0,
    };
};

const loadUsers = async () => {
    const response = await axios.get('/api/users');
    const apiUsers = response.data?.data?.users ?? [];

    users.value = apiUsers.filter((user) => {
        return user.id !== requesterId.value;
    });

    sanitizeStateForKnownUsers();
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
    selectUser({ id: request.from_user_id });
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

const selectUser = async (user) => {
    const userId = normalizeUserId(user?.id);

    selectedUserId.value = userId;

    if (!userId) {
        return;
    }

    unreadIncomingCounts.value = {
        ...unreadIncomingCounts.value,
        [userId]: 0,
    };

    await Promise.all([
        loadConversationForUser(userId),
        markConversationAsRead(userId),
    ]);
};

const closeChat = async (user) => {
    await axios.post('/api/chat-requests/close', {
        to_user_id: user.id,
    });

    setRequestState(user.id, 'none');

    unreadIncomingCounts.value = {
        ...unreadIncomingCounts.value,
        [user.id]: 0,
    };

    messageHistories.value = {
        ...messageHistories.value,
        [user.id]: [],
    };
};

const incrementUnreadIncoming = (userId) => {
    const currentCount = unreadIncomingCounts.value[userId] ?? 0;

    unreadIncomingCounts.value = {
        ...unreadIncomingCounts.value,
        [userId]: currentCount + 1,
    };
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
    const trimmedContent = String(content ?? '').trim();
    const isConnected = selectedUserRequestState.value === 'connected';

    if (!targetUser || !targetUser.is_online || !isConnected || trimmedContent.length === 0) {
        return;
    }

    const response = await axios.post('/api/chat-message/send', {
        to_user_id: targetUser.id,
        message: trimmedContent,
    });

    const chatMessage = response.data?.data?.chat_message;

    if (chatMessage) {
        appendMessageToHistory(targetUser.id, formatMessageLine(chatMessage));
    }
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

    const isConnectedUser = (requestStates.value[fromUserId] ?? 'none') === 'connected';
    const isSelectedConversation = Number(selectedUserId.value) === fromUserId;

    if (isConnectedUser && !isSelectedConversation) {
        incrementUnreadIncoming(fromUserId);
    }

    if (isSelectedConversation) {
        markConversationAsRead(fromUserId);
    }
};

onMounted(() => {
    restorePersistedState();

    loadUsers().then(() => {
        loadUnreadIncomingCounts();

        if (selectedUserId.value) {
            loadConversationForUser(Number(selectedUserId.value));
            markConversationAsRead(Number(selectedUserId.value));
        }
    });

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

watch(
    [requestStates, selectedUserId],
    () => {
        persistState();
    },
    { deep: true },
);

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
                                :unread-incoming-counts="unreadIncomingCounts"
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
                            :selected-user-request-state="selectedUserRequestState"
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