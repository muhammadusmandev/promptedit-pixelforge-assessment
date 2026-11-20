<script setup>
    import { ref } from 'vue';

    const message = ref('Laravel + Vue + Inertia is working!');
</script>

<template>
    <div class="min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-3xl font-bold">
                {{ message }}
            </h1>

            <p class="mt-4 text-gray-600">
                Welcome to your Laravel Vue application.
            </p>
        </div>
    </div>
</template>