<script setup>
defineProps({
    users: {
        type: Array,
        default: () => [],
    },
});
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
                class="rounded-md border border-gray-200 px-3 py-2"
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
            </li>
        </ul>
    </section>
</template>
