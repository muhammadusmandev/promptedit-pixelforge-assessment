<script setup>
    import { Link } from '@inertiajs/vue3';
    import AppLayout from './Layouts/AppLayout.vue';

    defineProps({
        user: Object,
    });
</script>

<template>
    <AppLayout>
        <main className="max-w-6xl mx-auto p-8">
        
            <!--- Welcome Section --->
            <div className="mb-10">
                <p className="text-sm font-medium text-indigo-600 mb-2">
                    AI IMAGE STUDIO
                </p>
        
                <h2 className="text-3xl font-bold text-gray-900">
                    Welcome back, {{user.name}}! 👋
                </h2>
        
                <p className="text-gray-500 mt-2">
                    Ready to bring your creative ideas to life
                </p>
            </div>
        
            <!--- Hero Section --->
            <div v-if="!$page.props.auth.user.is_admin"
                className="relative overflow-hidden rounded-2xl p-8 md:p-10 mb-8 shadow-lg border border-gray-200 bg-white">
        
                <div className="relative z-10 max-w-2xl">
                    <span className="text-gray-500 text-sm font-medium">
                        YOUR CREATIVE WORKSPACE
                    </span>
        
                    <h3 className="text-3xl md:text-4xl font-bold text-gray-900 mt-3">
                        Turn your imagination into reality.
                    </h3>
        
                    <p className="text-gray-500 mt-4 text-base leading-relaxed">
                        Create stunning AI-generated images with just a simple
                        text prompt. Your next masterpiece is one click away.
                    </p>
        
                    <div className="flex flex-wrap gap-3 mt-7">
                        <Link href="/generations/create"
                            className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold hover:bg-blue-500 transition shadow-md">
                        ✨ Generate Image
                        </Link>
        
                        <Link href="/generations"
                            className="bg-gray/10 border border-gray-300 text-gray-900 px-6 py-3 rounded-xl font-semibold hover:bg-gray-100 transition">
                        View Generation History →
                        </Link>
                    </div>
                </div>
        
            </div>
        </main>    
    </AppLayout>
</template>