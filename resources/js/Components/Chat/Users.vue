<script setup>
const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    requestStates: {
        type: Object,
        default: () => ({}),
    },
    selectedUserId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['request-chat', 'dismiss-declined', 'close-chat', 'select-user']);

const canRequestChat = (user) => {
    const state = props.requestStates[user.id] ?? 'none';

    return user.is_online && state !== 'connected' && state !== 'pending';
};

const userState = (user) => props.requestStates[user.id] ?? 'none';
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Users</h2>

        <div v-if="users.length === 0" class="mt-3 text-sm text-gray-500">
            No users available.
        </div>

        <ul v-else class="mt-3 space-y-2">
            <li
                v-for="user in users"
                :key="user.id"
                class="cursor-pointer rounded-md border px-3 py-2"
                :class="props.selectedUserId === user.id
                    ? 'border-blue-400 bg-blue-50'
                    : 'border-gray-200'"
                @click="emit('select-user', user)"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ user.name }}</p>
                        <p class="text-xs text-gray-500">{{ user.email }}</p>
                    </div>

                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="user.is_online
                            ? 'bg-green-100 text-green-700'
                            : 'bg-red-100 text-red-700'"
                    >
                        {{ user.is_online ? 'Online' : 'Offline' }}
                    </span>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button
                        v-if="userState(user) !== 'connected'"
                        type="button"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canRequestChat(user)"
                        @click.stop="emit('request-chat', user)"
                    >
                        Chat Request
                    </button>

                    <button
                        v-else
                        type="button"
                        class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white"
                        @click.stop="emit('close-chat', user)"
                    >
                        Close Chat
                    </button>

                    <span
                        v-if="userState(user) === 'connected'"
                        class="text-xs text-blue-600"
                    >
                        chating...
                    </span>
                </div>

                <div
                    v-if="userState(user) === 'declined' || userState(user) === 'closed'"
                    class="mt-2 flex items-center justify-between gap-2 rounded-md bg-[bisque] p-2 text-xs text-gray-700"
                >
                    <span>{{ userState(user) === 'closed' ? 'Chat Closed' : 'Request Declined' }}</span>
                    <button
                        type="button"
                        class="rounded-md bg-blue-600 px-2 py-1 text-xs text-white"
                        @click.stop="emit('dismiss-declined', user)"
                    >
                        Dismiss
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>
