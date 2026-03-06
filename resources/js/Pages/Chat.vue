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
const chatRooms = ref([]);
const roomInvites = ref([]);
const roomNotices = ref([]);

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

/**
 * Normalize an incoming chat message object to the UI message shape.
 *
 * Logic:
 * 1) Coerce IDs to positive integers when valid.
 * 2) Normalize optional fields to safe defaults.
 * 3) Keep receipt-related fields (`is_mine`, `read_at`) for rendering.
 *
 * @param {Record<string, any>} chatMessage
 * @returns {{
 *   id: number|null,
 *   from_user_id: number|null,
 *   to_user_id: number|null,
 *   from_user_name: string,
 *   message: string,
 *   is_mine: boolean,
 *   read_at: string|null,
 *   created_at: string|null
 * }}
 */
const normalizeMessage = (chatMessage) => {
    const id = Number(chatMessage?.id ?? 0);
    const fromUserId = Number(chatMessage?.from_user_id ?? 0);
    const toUserId = Number(chatMessage?.to_user_id ?? 0);

    return {
        id: Number.isInteger(id) && id > 0 ? id : null,
        from_user_id: Number.isInteger(fromUserId) && fromUserId > 0 ? fromUserId : null,
        to_user_id: Number.isInteger(toUserId) && toUserId > 0 ? toUserId : null,
        from_user_name: String(chatMessage?.from_user_name ?? 'User'),
        message: String(chatMessage?.message ?? ''),
        is_mine: Boolean(chatMessage?.is_mine),
        read_at: chatMessage?.read_at ?? null,
        created_at: chatMessage?.created_at ?? null,
    };
};

/**
 * Replace one user's conversation history with normalized messages.
 *
 * Logic:
 * 1) Normalize each raw API message.
 * 2) Update only selected user's history key in reactive map.
 *
 * @param {number} userId
 * @param {Array<Record<string, any>>} messages
 * @returns {void}
 */
const setConversationHistory = (userId, messages) => {
    messageHistories.value = {
        ...messageHistories.value,
        [userId]: messages.map(normalizeMessage),
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

const loadChatRooms = async () => {
    const response = await axios.get('/api/chat-rooms');

    chatRooms.value = response.data?.data?.rooms ?? [];
};

const loadRoomInvites = async () => {
    const response = await axios.get('/api/chat-rooms/invites');

    roomInvites.value = response.data?.data?.invites ?? [];
};

const createChatRoom = async (payload) => {
    const response = await axios.post('/api/chat-rooms', {
        name: String(payload?.name ?? '').trim(),
        user_ids: Array.isArray(payload?.user_ids) ? payload.user_ids : [],
    });

    const chatRoom = response.data?.data?.chat_room;

    if (chatRoom) {
        chatRooms.value = [chatRoom, ...chatRooms.value];
    }
};

const removeRoomInviteFromState = (inviteId) => {
    roomInvites.value = roomInvites.value.filter((invite) => Number(invite.id) !== Number(inviteId));
};

const removeChatRoomFromState = (chatRoomId) => {
    chatRooms.value = chatRooms.value.filter((chatRoom) => Number(chatRoom.id) !== Number(chatRoomId));
};

const addRoomNotice = (message, roomId = null) => {
    if (!message) {
        return;
    }

    const noticeId = `room-notice-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

    roomNotices.value = [
        {
            id: noticeId,
            room_id: roomId,
            message: String(message),
        },
        ...roomNotices.value,
    ];
};

const dismissRoomNotice = (notice) => {
    const noticeId = String(notice?.id ?? '');

    roomNotices.value = roomNotices.value.filter((roomNotice) => String(roomNotice.id) !== noticeId);
};

const respondRoomInvite = async (invite, action) => {
    const inviteId = Number(invite?.id ?? 0);

    if (!inviteId || !['accept', 'decline'].includes(action)) {
        return;
    }

    const response = await axios.post(`/api/chat-rooms/invites/${inviteId}/respond`, {
        action,
    });

    const chatRoom = response.data?.data?.chat_room ?? null;

    if (action === 'accept' && chatRoom) {
        chatRooms.value = [
            chatRoom,
            ...chatRooms.value.filter((existingRoom) => Number(existingRoom.id) !== Number(chatRoom.id)),
        ];
    }

    removeRoomInviteFromState(inviteId);
};

const acceptRoomInvite = async (invite) => {
    await respondRoomInvite(invite, 'accept');
};

const declineRoomInvite = async (invite) => {
    await respondRoomInvite(invite, 'decline');
};

const closeChatRoom = async (chatRoom) => {
    const chatRoomId = Number(chatRoom?.id ?? 0);

    if (!chatRoomId) {
        return;
    }

    await axios.post(`/api/chat-rooms/${chatRoomId}/close`);

    removeChatRoomFromState(chatRoomId);
};

const leaveChatRoom = async (chatRoom) => {
    const chatRoomId = Number(chatRoom?.id ?? 0);

    if (!chatRoomId) {
        return;
    }

    await axios.post(`/api/chat-rooms/${chatRoomId}/leave`);

    removeChatRoomFromState(chatRoomId);
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

/**
 * Append one message to a conversation, skipping duplicates by message ID.
 *
 * Logic:
 * 1) Normalize incoming message payload.
 * 2) Skip insert when same message ID already exists.
 * 3) Append new message to the target user's history list.
 *
 * @param {number} userId
 * @param {Record<string, any>} chatMessage
 * @returns {void}
 */
const appendMessageToHistory = (userId, chatMessage) => {
    const existing = messageHistories.value[userId] ?? [];
    const normalizedMessage = normalizeMessage(chatMessage);

    if (normalizedMessage.id && existing.some((message) => message.id === normalizedMessage.id)) {
        return;
    }

    messageHistories.value = {
        ...messageHistories.value,
        [userId]: [...existing, normalizedMessage],
    };
};

/**
 * Apply read-receipt timestamp updates to matching outgoing messages.
 *
 * Logic:
 * 1) Normalize and validate incoming message ID list.
 * 2) Update only messages that are mine and whose IDs are listed.
 * 3) Persist merged history back into reactive conversation map.
 *
 * @param {number} userId
 * @param {Array<number|string>} messageIds
 * @param {string|null|undefined} readAt
 * @returns {void}
 */
const applyReadReceiptToHistory = (userId, messageIds, readAt) => {
    const existing = messageHistories.value[userId] ?? [];
    const normalizedMessageIds = new Set(
        (messageIds ?? [])
            .map((messageId) => Number(messageId))
            .filter((messageId) => Number.isInteger(messageId) && messageId > 0),
    );

    if (normalizedMessageIds.size === 0 || existing.length === 0) {
        return;
    }

    const nextHistory = existing.map((chatMessage) => {
        if (!chatMessage.is_mine || !chatMessage.id || !normalizedMessageIds.has(chatMessage.id)) {
            return chatMessage;
        }

        return {
            ...chatMessage,
            read_at: readAt ?? chatMessage.read_at ?? new Date().toISOString(),
        };
    });

    messageHistories.value = {
        ...messageHistories.value,
        [userId]: nextHistory,
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
        appendMessageToHistory(targetUser.id, chatMessage);
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

    if (type === 'chat_room_invited') {
        const inviteId = Number(event?.invite_id ?? 0);
        const roomId = Number(event?.room_id ?? 0);

        if (!inviteId || !roomId) {
            return;
        }

        const alreadyExists = roomInvites.value.some((invite) => Number(invite.id) === inviteId);

        if (alreadyExists) {
            return;
        }

        roomInvites.value = [
            {
                id: inviteId,
                chat_room_id: roomId,
                chat_room_name: String(event?.room_name ?? 'Chat Room'),
                from_user_id: fromUserId,
                from_user_name: String(event?.from_user_name ?? 'User'),
            },
            ...roomInvites.value,
        ];

        return;
    }

    if (type === 'chat_room_invite_accepted') {
        loadChatRooms();
        return;
    }

    if (type === 'chat_room_closed') {
        const roomId = Number(event?.room_id ?? 0);
        const requesterName = String(event?.from_user_name ?? 'User');
        const roomNameFromEvent = String(event?.room_name ?? '').trim();
        const roomNameFromState = chatRooms.value.find((chatRoom) => Number(chatRoom.id) === roomId)?.name ?? '';
        const roomName = roomNameFromEvent || String(roomNameFromState).trim() || 'Chat Room';

        if (roomId) {
            removeChatRoomFromState(roomId);
        }

        addRoomNotice(`Chat room ${roomName} was closed by ${requesterName}`, roomId || null);
        return;
    }

    if (type === 'chat_room_user_left') {
        const roomId = Number(event?.room_id ?? 0);
        const leftUserName = String(event?.from_user_name ?? 'User');
        const roomName = String(event?.room_name ?? 'Chat Room');

        loadChatRooms();
        addRoomNotice(`User ${leftUserName} has left the chat room ${roomName}`, roomId || null);
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
    const message = String(event?.message ?? '').trim();

    if (!fromUserId || message.length === 0) {
        return;
    }

    appendMessageToHistory(fromUserId, {
        id: Number(event?.id ?? 0),
        from_user_id: fromUserId,
        from_user_name: String(event?.from_user_name ?? 'User'),
        to_user_id: requesterId.value,
        message,
        is_mine: false,
        read_at: null,
        created_at: event?.created_at ?? null,
    });

    const isConnectedUser = (requestStates.value[fromUserId] ?? 'none') === 'connected';
    const isSelectedConversation = Number(selectedUserId.value) === fromUserId;

    if (isConnectedUser && !isSelectedConversation) {
        incrementUnreadIncoming(fromUserId);
    }

    if (isSelectedConversation) {
        markConversationAsRead(fromUserId);
    }
};

/**
 * Handle realtime read-receipt events and apply them to local history.
 *
 * Logic:
 * 1) Validate reader ID and message ID list from event payload.
 * 2) Apply read receipt updates to that conversation history.
 *
 * @param {Record<string, any>} event
 * @returns {void}
 */
const handleChatMessagesRead = (event) => {
    const readerUserId = Number(event?.reader_user_id ?? 0);
    const messageIds = Array.isArray(event?.message_ids) ? event.message_ids : [];

    if (!readerUserId || messageIds.length === 0) {
        return;
    }

    applyReadReceiptToHistory(readerUserId, messageIds, event?.read_at ?? null);
};

onMounted(() => {
    restorePersistedState();

    loadUsers().then(() => {
        loadUnreadIncomingCounts();
        loadChatRooms();
        loadRoomInvites();

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
                .listen('.chat.message.sent', handleChatMessageSent)
                .listen('.chat.messages.read', handleChatMessagesRead);
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
                .stopListening('.chat.message.sent', handleChatMessageSent)
                .stopListening('.chat.messages.read', handleChatMessagesRead);
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
                            <ChatRooms
                                :chat-rooms="chatRooms"
                                :room-invites="roomInvites"
                                :room-notices="roomNotices"
                                :users="users"
                                :authenticated-user-id="requesterId"
                                @create-chat-room="createChatRoom"
                                @accept-room-invite="acceptRoomInvite"
                                @decline-room-invite="declineRoomInvite"
                                @close-chat-room="closeChatRoom"
                                @confirm-leave-chat-room="leaveChatRoom"
                                @dismiss-room-notice="dismissRoomNotice"
                            />
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
