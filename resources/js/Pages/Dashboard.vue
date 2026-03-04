<script setup>
import Chat from '@/Pages/Chat.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <Chat :users="page.props.users ?? []" />
</template>
