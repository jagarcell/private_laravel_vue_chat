<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    chatRooms: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
    authenticatedUserId: {
        type: Number,
        default: null,
    },
    roomInvites: {
        type: Array,
        default: () => [],
    },
    roomNotices: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'create-chat-room',
    'accept-room-invite',
    'decline-room-invite',
    'close-chat-room',
    'confirm-leave-chat-room',
    'dismiss-room-notice',
]);

const isCreateDialogOpen = ref(false);
const roomName = ref('');
const selectedUserIds = ref([]);
const pendingLeaveRoomId = ref(null);

const canCreateRoom = computed(() => {
    return roomName.value.trim().length > 0 && selectedUserIds.value.length > 0;
});

const activeRoomIds = computed(() => {
    return new Set(
        props.chatRooms
            .map((chatRoom) => Number(chatRoom?.id ?? 0))
            .filter((chatRoomId) => chatRoomId > 0),
    );
});

const orphanRoomNotices = computed(() => {
    return props.roomNotices.filter((notice) => {
        const roomId = Number(notice?.room_id ?? 0);

        if (!roomId) {
            return true;
        }

        return !activeRoomIds.value.has(roomId);
    });
});

const selectableUsers = computed(() => {
    const authenticatedUserId = Number(props.authenticatedUserId);

    if (!authenticatedUserId) {
        return props.users;
    }

    return props.users.filter((user) => Number(user.id) !== authenticatedUserId);
});

const openCreateDialog = () => {
    isCreateDialogOpen.value = true;
};

const closeCreateDialog = () => {
    isCreateDialogOpen.value = false;
    roomName.value = '';
    selectedUserIds.value = [];
};

const toggleSelectedUser = (userId) => {
    const normalizedUserId = Number(userId);

    if (selectedUserIds.value.includes(normalizedUserId)) {
        selectedUserIds.value = selectedUserIds.value.filter((id) => id !== normalizedUserId);
        return;
    }

    selectedUserIds.value = [...selectedUserIds.value, normalizedUserId];
};

const createRoom = () => {
    if (!canCreateRoom.value) {
        return;
    }

    emit('create-chat-room', {
        name: roomName.value.trim(),
        user_ids: selectedUserIds.value.filter((userId) => Number(userId) !== Number(props.authenticatedUserId)),
    });

    closeCreateDialog();
};

const roomParticipantsLabel = (chatRoom) => {
    const users = (Array.isArray(chatRoom?.users) ? chatRoom.users : [])
        .filter((user) => Number(user.id) !== Number(props.authenticatedUserId));

    if (users.length === 0) {
        return 'No other participants';
    }

    return users.map((user) => user.name).join(', ');
};

const isCreator = (chatRoom) => {
    return Number(chatRoom?.created_by_user_id) === Number(props.authenticatedUserId);
};

const promptLeaveRoom = (chatRoom) => {
    pendingLeaveRoomId.value = Number(chatRoom?.id ?? 0) || null;
};

const clearLeavePrompt = () => {
    pendingLeaveRoomId.value = null;
};

const confirmLeaveRoom = (chatRoom) => {
    emit('confirm-leave-chat-room', chatRoom);
    clearLeavePrompt();
};

const noticesForRoom = (chatRoomId) => {
    const normalizedRoomId = Number(chatRoomId ?? 0);

    if (!normalizedRoomId) {
        return [];
    }

    return props.roomNotices.filter((notice) => Number(notice?.room_id ?? 0) === normalizedRoomId);
};
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Chat Rooms</h2>
            <button
                type="button"
                class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white"
                @click="openCreateDialog"
            >
                NEW
            </button>
        </div>

        <div
            v-if="chatRooms.length === 0"
            class="mt-3 text-sm text-gray-500"
        >
            No chat rooms created yet.
        </div>

        <ul
            v-else
            class="mt-3 space-y-2"
        >
            <li
                v-for="chatRoom in chatRooms"
                :key="chatRoom.id"
                class="rounded-md border border-gray-200 p-3"
            >
                <p class="text-sm font-medium text-gray-900">{{ chatRoom.name }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ roomParticipantsLabel(chatRoom) }}</p>
                <div class="mt-3">
                    <button
                        v-if="isCreator(chatRoom)"
                        type="button"
                        class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white"
                        @click="emit('close-chat-room', chatRoom)"
                    >
                        Close
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-md bg-gray-700 px-3 py-1.5 text-xs font-medium text-white"
                        @click="promptLeaveRoom(chatRoom)"
                    >
                        Leave
                    </button>
                </div>
                <div
                    v-if="!isCreator(chatRoom) && pendingLeaveRoomId === Number(chatRoom.id)"
                    class="mt-3 rounded-md border border-gray-200 bg-[bisque] p-3"
                >
                    <p class="text-sm text-gray-700">You are leaving the chat room</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white"
                            @click="confirmLeaveRoom(chatRoom)"
                        >
                            Confirm
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700"
                            @click="clearLeavePrompt"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
                <div
                    v-if="noticesForRoom(chatRoom.id).length > 0"
                    class="mt-3 space-y-2"
                >
                    <div
                        v-for="notice in noticesForRoom(chatRoom.id)"
                        :key="notice.id"
                        class="rounded-md border border-gray-200 bg-[bisque] p-3"
                    >
                        <p class="text-sm text-gray-700">
                            {{ notice.message }}
                        </p>
                        <div class="mt-3">
                            <button
                                type="button"
                                class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white"
                                @click="emit('dismiss-room-notice', notice)"
                            >
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <div
            v-if="roomInvites.length > 0"
            class="mt-3 space-y-2"
        >
            <div
                v-for="invite in roomInvites"
                :key="invite.id"
                class="rounded-md border border-gray-200 bg-[bisque] p-3"
            >
                <p class="text-sm text-gray-700">
                    {{ invite.from_user_name }} has invited you to join {{ invite.chat_room_name }} chat room
                </p>
                <div class="mt-3 flex gap-2">
                    <button
                        type="button"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white"
                        @click="emit('accept-room-invite', invite)"
                    >
                        Accept
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white"
                        @click="emit('decline-room-invite', invite)"
                    >
                        Decline
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="orphanRoomNotices.length > 0"
            class="mt-3 space-y-2"
        >
            <div
                v-for="notice in orphanRoomNotices"
                :key="notice.id"
                class="rounded-md border border-gray-200 bg-[bisque] p-3"
            >
                <p class="text-sm text-gray-700">
                    {{ notice.message }}
                </p>
                <div class="mt-3">
                    <button
                        type="button"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white"
                        @click="emit('dismiss-room-notice', notice)"
                    >
                        Dismiss
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="isCreateDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closeCreateDialog"
        >
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Create Chat Room</h3>

                <div class="mt-4">
                    <label
                        for="chat-room-name"
                        class="text-xs font-medium uppercase tracking-wide text-gray-600"
                    >
                        Chat Room Name
                    </label>
                    <input
                        id="chat-room-name"
                        v-model="roomName"
                        type="text"
                        class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm text-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Enter room name"
                    />
                </div>

                <div class="mt-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-600">
                        Select Users
                    </p>

                    <div class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-2">
                        <label
                            v-for="user in selectableUsers"
                            :key="user.id"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 hover:bg-gray-50"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedUserIds.includes(user.id)"
                                @change="toggleSelectedUser(user.id)"
                            />
                            <span class="text-sm text-gray-700">{{ user.name }} ({{ user.email }})</span>
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700"
                        @click="closeCreateDialog"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canCreateRoom"
                        @click="createRoom"
                    >
                        CREATE
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
