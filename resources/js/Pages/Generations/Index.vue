<script setup>
    import { computed, onMounted, onUnmounted, ref } from 'vue';
    import { Link, router } from '@inertiajs/vue3';

    import AppLayout from '../Layouts/AppLayout.vue';
    import {
        formatCost,
        statusClass,
        statusLabel,
    } from '../../utils/generation';

    const props = defineProps({
        generations: {
            type: Object,
            required: true,
        },
    });

    // State
    let pollingInterval = null;
    let isReloading = false;

    const failedImages = ref(new Set());

    // --------------------------------------------------
    // Computed
    // --------------------------------------------------
    const hasPendingGenerations = computed(() => {
        return props.generations.data.some(generation =>
            ['queued', 'processing'].includes(generation.status)
        );
    });

    // --------------------------------------------------
    // Image Handling
    // Handle expired or unavailable Pixelforge image URLs.
    // --------------------------------------------------
    const markImageUnavailable = (id) => {
        failedImages.value = new Set([
            ...failedImages.value,
            id,
        ]);
    };

    // --------------------------------------------------
    // Automatic Generation Refresh
    // Poll only while there are unfinished generations.
    // --------------------------------------------------
    const refreshGenerations = () => {
        if (!hasPendingGenerations.value || isReloading) {
            return;
        }

        isReloading = true;

        router.reload({
            only: ['generations'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isReloading = false;
            },
        });
    };

    // --------------------------------------------------
    // Lifecycle
    // --------------------------------------------------
    onMounted(() => {
        pollingInterval = setInterval(
            refreshGenerations,
            5000
        );
    });

    onUnmounted(() => {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    });
</script>

<template>
    <AppLayout>
        <div class="max-w-6xl mx-auto p-8">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">

                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        My Generations
                    </h1>

                    <p class="text-gray-500 mt-2">
                        View your generated images, generation status
                        and costs.
                    </p>
                </div>

                <Link href="/generations/create" class="inline-flex justify-center items-center bg-blue-600
                           hover:bg-blue-700 text-white px-5 py-3 rounded-lg
                           font-medium transition">
                + Generate Image
                </Link>

            </div>

            <!-- Flash success -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-700
                       p-4 rounded-lg mb-5">
                {{ $page.props.flash.success }}
            </div>

            <!-- Flash warning -->
            <div v-if="$page.props.flash?.warning" class="bg-yellow-50 border border-yellow-200 text-yellow-800
                       p-4 rounded-lg mb-5">
                {{ $page.props.flash.warning }}
            </div>

            <!-- Live update indicator -->
            <div v-if="hasPendingGenerations" class="flex items-center gap-2 text-sm text-blue-700 mb-5">
                <span class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>

                Automatically checking generation progress...
            </div>

            <!-- Empty state -->
            <div v-if="!generations.data.length" class="bg-white border border-gray-200 rounded-xl p-12 text-center">
                <div class="text-4xl mb-4">
                    🖼️
                </div>

                <h2 class="text-xl font-semibold text-gray-800">
                    No generations yet
                </h2>

                <p class="text-gray-500 mt-2">
                    Your generated images will appear here.
                </p>

                <Link href="/generations/create" class="inline-block mt-5 bg-blue-600 text-white
                           px-5 py-3 rounded-lg hover:bg-blue-700">
                Generate your first image
                </Link>
            </div>

            <!-- Generation list -->
            <div v-else class="space-y-5">

                <div v-for="generation in generations.data" :key="generation.id" class="bg-white border border-gray-200 rounded-xl
                           shadow-sm overflow-hidden">

                    <div class="p-5">

                        <div class="flex flex-col md:flex-row gap-5">

                            <!-- Generated image -->
                            <div class="w-full md:w-52 flex-shrink-0">

                                <div class="aspect-square bg-gray-100 rounded-lg
                                           overflow-hidden flex items-center
                                           justify-center">

                                    <img v-if="
                                            generation.image_url &&
                                            !failedImages.has(generation.id)
                                        " :src="generation.image_url" :alt="'Generated image: ' + generation.prompt"
                                        class="w-full h-full object-cover" loading="lazy"
                                        @error="markImageUnavailable(generation.id)" />

                                    <div v-else-if="generation.status === 'queued' ||
                                                  generation.status === 'processing'" class="text-center p-5">
                                        <div class="animate-pulse text-3xl mb-3">
                                            🎨
                                        </div>

                                        <p class="text-sm text-gray-500">
                                            Image is being generated...
                                        </p>
                                    </div>

                                    <div v-else-if="generation.status === 'failed'" class="text-center p-5">
                                        <div class="text-3xl mb-3">
                                            ⚠️
                                        </div>

                                        <p class="text-sm text-red-500">
                                            Generation failed
                                        </p>
                                    </div>

                                    <div v-else class="text-center p-5">
                                        <div class="text-3xl mb-3">
                                            🖼️
                                        </div>

                                        <p class="text-sm text-gray-500">
                                            Image unavailable
                                        </p>
                                    </div>

                                </div>

                            </div>

                            <!-- Generation details -->
                            <div class="flex-1 min-w-0">

                                <!-- Status and date -->
                                <div class="flex flex-wrap justify-between
                                            items-center gap-3">

                                    <span class="inline-flex items-center
                                               text-xs font-semibold px-3 py-2
                                               rounded-full" :class="statusClass(generation.status)">
                                        {{ statusLabel(generation.status) }}
                                    </span>

                                    <span class="text-xs text-gray-400">
                                        {{ generation.created_at }}
                                    </span>

                                </div>

                                <!-- Prompt -->
                                <h2 class="font-semibold text-gray-900 mt-4
                                           break-words">
                                    {{ generation.prompt }}
                                </h2>

                                <!-- Settings -->
                                <div class="flex flex-wrap gap-2 mt-3">

                                    <span class="bg-gray-100 text-gray-600
                                               text-xs px-3 py-1.5 rounded-md">
                                        {{ generation.size || 'Default size' }}
                                    </span>

                                    <span class="bg-gray-100 text-gray-600
                                               text-xs px-3 py-1.5 rounded-md
                                               capitalize">
                                        {{ generation.quality || 'Default quality' }}
                                    </span>

                                </div>

                                <!-- Cost information -->
                                <div class="grid grid-cols-1 sm:grid-cols-2
                                            gap-3 mt-5">

                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">

                                        <p class="text-xs text-gray-500">
                                            Estimated Cost (With 25% Markup)
                                        </p>

                                        <p class="text-lg font-bold text-gray-900 mt-1">
                                            {{ formatCost(generation.estimated_cost) }}
                                        </p>

                                    </div>

                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">

                                        <p class="text-xs text-gray-500">
                                            Actual/Billed Cost (With 25% Markup)
                                        </p>

                                        <p class="text-lg font-bold text-gray-900 mt-1">
                                            {{ formatCost(generation.actual_cost) }}
                                        </p>

                                    </div>

                                </div>

                                <!-- Completion date -->
                                <p v-if="generation.completed_at" class="text-xs text-gray-400 mt-4">
                                    Completed: {{ generation.completed_at }}
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Pagination -->
            <div v-if="generations.meta?.links?.length > 3" class="flex flex-wrap gap-2 justify-end mt-8">
                <Link v-for="(link, index) in generations.meta.links" :key="index" :href="link.url || '#'" :preserve-scroll="true"
                    :class="[
                        'px-3 py-2 border rounded-lg text-sm transition',
                        link.active
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-gray-700 hover:bg-gray-100',
                        !link.url
                            ? 'opacity-40 pointer-events-none'
                            : ''
                    ]">
                <span v-html="link.label"></span>
                </Link>
            </div>

        </div>

    </AppLayout>
</template>