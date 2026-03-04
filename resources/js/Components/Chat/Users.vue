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
                <p class="text-sm font-medium text-gray-900">{{ user.name }}</p>
                <p class="text-xs text-gray-500">{{ user.email }}</p>
            </li>
        </ul>
    </section>
</template>
