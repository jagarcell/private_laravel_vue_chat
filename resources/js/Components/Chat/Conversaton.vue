<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    incomingRequest: {
        type: Object,
        default: null,
    },
    selectedUser: {
        type: Object,
        default: null,
    },
    selectedUserRequestState: {
        type: String,
        default: 'none',
    },
    messageHistory: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['accept-request', 'decline-request', 'send-message']);

const messageInput = ref('');
const historyContainer = ref(null);
const canSendMessage = computed(() => {
    const hasMessage = messageInput.value.trim().length > 0;
    const hasOnlineSelectedUser = Boolean(props.selectedUser?.is_online);
    const hasConnectedSelectedUser = props.selectedUserRequestState === 'connected';

    return hasMessage && hasOnlineSelectedUser && hasConnectedSelectedUser;
});

const sendMessage = () => {
    if (!canSendMessage.value) {
        return;
    }

    const content = messageInput.value.trim();

    emit('send-message', content);
    messageInput.value = '';
};

const scrollHistoryToBottom = async () => {
    await nextTick();

    if (!historyContainer.value) {
        return;
    }

    historyContainer.value.scrollTop = historyContainer.value.scrollHeight;
};

watch(
    () => props.messageHistory.length,
    (newLength, oldLength) => {
        if (newLength > oldLength) {
            scrollHistoryToBottom();
        }
    },
);

onMounted(() => {
    scrollHistoryToBottom();
});
</script>

<template>
    <section class="h-full rounded-lg border border-gray-200 bg-white p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Conversation</h2>

        <div
            v-if="incomingRequest"
            class="mt-4 rounded-md border border-gray-200 bg-[bisque] p-4"
        >
            <p class="text-sm text-gray-700">
                {{ incomingRequest.from_user_name }} sent you a chat request.
            </p>

            <div class="mt-3 flex gap-2">
                <button
                    type="button"
                    class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white"
                    @click="emit('accept-request', incomingRequest)"
                >
                    Accept
                </button>
                <button
                    type="button"
                    class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white"
                    @click="emit('decline-request', incomingRequest)"
                >
                    Decline
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-3">
            <div
                ref="historyContainer"
                class="h-56 space-y-2 overflow-y-auto rounded-md border border-gray-300 bg-white p-2"
            >
                <p
                    v-if="messageHistory.length === 0"
                    class="text-sm text-gray-500"
                >
                    No messages yet.
                </p>

                <div
                    v-for="(chatMessage, index) in messageHistory"
                    :key="chatMessage.id ?? `message-${index}`"
                    class="flex"
                    :class="chatMessage.is_mine ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[80%] rounded-lg px-3 py-2 text-sm"
                        :class="chatMessage.is_mine ? 'text-gray-800' : 'bg-gray-100 text-gray-800'"
                    >
                        <p
                            v-if="!chatMessage.is_mine"
                            class="mb-1 text-xs font-semibold text-gray-500"
                        >
                            {{ chatMessage.from_user_name ?? 'User' }}
                        </p>
                        <div
                            v-if="chatMessage.is_mine"
                            class="flex items-end justify-end gap-1"
                        >
                            <div class="flex flex-col items-start">
                                <p class="mb-1 text-xs font-semibold text-gray-500">
                                    You:
                                </p>
                                <p class="whitespace-pre-wrap break-words text-right">
                                    {{ chatMessage.message }}
                                </p>
                            </div>
                            <span
                                v-if="chatMessage.read_at"
                                class="flex items-center text-blue-400"
                                aria-label="Read"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L9 11.586l6.293-6.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <svg
                                    class="-ml-1 h-4 w-4"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L9 11.586l6.293-6.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </span>
                            <svg
                                v-else
                                class="h-4 w-4 text-gray-400"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-label="Sent"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L9 11.586l6.293-6.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <p
                            v-else
                            class="whitespace-pre-wrap break-words"
                        >
                            {{ chatMessage.message }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
                <input
                    v-model="messageInput"
                    type="text"
                    class="w-full rounded-md border border-gray-300 p-2 text-sm text-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Type your message"
                    @keyup.enter="sendMessage"
                />
                <button
                    type="button"
                    class="rounded-md bg-blue-600 px-3 py-2 text-xs font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canSendMessage"
                    @click="sendMessage"
                >
                    Send
                </button>
            </div>
        </div>
    </section>
</template>
