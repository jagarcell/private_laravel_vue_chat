<script setup>
defineProps({
    incomingRequest: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['accept-request', 'decline-request']);
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

        <div v-else class="mt-4 flex h-[calc(100%-2rem)] items-center justify-center rounded-md border border-dashed border-gray-300 bg-gray-50 text-sm text-gray-500">
            Select a user or a room to start chatting.
        </div>
    </section>
</template>
