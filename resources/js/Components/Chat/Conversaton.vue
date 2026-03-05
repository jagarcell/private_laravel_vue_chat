<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    incomingRequest: {
        type: Object,
        default: null,
    },
    selectedUser: {
        type: Object,
        default: null,
    },
    messageHistory: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['accept-request', 'decline-request', 'send-message']);

const messageInput = ref('');
const canSendMessage = computed(() => {
    const hasMessage = messageInput.value.trim().length > 0;
    const hasOnlineSelectedUser = Boolean(props.selectedUser?.is_online);

    return hasMessage && hasOnlineSelectedUser;
});

const messageHistoryText = computed(() => {
    if (props.messageHistory.length === 0) {
        return 'No messages yet.';
    }

    return props.messageHistory.join('\n');
});

const sendMessage = () => {
    if (!canSendMessage.value) {
        return;
    }

    const content = messageInput.value.trim();

    emit('send-message', content);
    messageInput.value = '';
};
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
            <textarea
                class="h-56 w-full resize-none rounded-md border border-gray-300 bg-white p-2 text-sm text-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                :value="messageHistoryText"
                readonly
            />

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
