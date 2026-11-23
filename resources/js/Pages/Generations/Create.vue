<script setup>
    import { useForm, Link } from '@inertiajs/vue3';
    import AppLayout from '../Layouts/AppLayout.vue';

    import { useGenerationPricing } from '../../composables/useGenerationPricing';
    import { isValidReferenceImageUrl } from '../../utils/generationValidation';

    const maxImages = 4;

    const form = useForm({
        prompt: '',
        size: '1024x1024',
        quality: 'medium',
        reference_images: [],
    });

    // Pricing
    const {
        priceStatus,
        pricePreview,
        priceError,
        calculatePricing,
    } = useGenerationPricing(form, maxImages);

    // Reference images
    const addImage = () => {
        if (form.reference_images.length < maxImages) {
            form.reference_images.push('');
        }
    };

    const removeImage = (index) => {
        form.reference_images.splice(index, 1);
    };

    // Form submission
    const submit = () => {
        form.clearErrors();

        if (!form.prompt.trim()) {
            form.setError('prompt', 'Please enter a prompt.');
            return;
        }

        if (form.prompt.length > 4000) {
            form.setError('prompt', 'Maximum 4,000 characters allowed.');
            return;
        }

        if (form.reference_images.length > maxImages) {
            form.setError('reference_images', 'Maximum 4 images allowed.');
            return;
        }

        for (let i = 0; i < form.reference_images.length; i++) {
            const url = form.reference_images[i].trim();

            if (!url) {
                form.setError(
                    `reference_images.${i}`,
                    'Please enter an image URL or remove this field.'
                );
                return;
            }

            if (!isValidReferenceImageUrl(url)) {
                form.setError(
                    `reference_images.${i}`,
                    'Please enter a valid HTTP or HTTPS URL.'
                );
                return;
            }
        }

        // Never submit without a successful latest price calculation.
        if (
            priceStatus.value !== 'ready' ||
            !pricePreview.value ||
            form.processing
        ) {
            return;
        }

        form.post('/generations', {
            preserveScroll: true,
        });
    };
</script>


<template>
    <AppLayout>

        <div class="max-w-6xl mx-auto p-8">

            <!-- Header -->
            <div class="mb-8">
                <Link href="/generations" class="text-sm text-gray-500 hover:text-gray-800">
                ← Back to History
                </Link>

                <h1 class="text-3xl font-bold text-gray-900 mt-4">
                    Generate Image
                </h1>

                <p class="text-gray-500 mt-2">
                    Describe your idea and let AI bring it to life.
                </p>

                <div v-if="form.errors.generation || form.errors.pricing"
                    class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg">
                    {{ form.errors.generation || form.errors.pricing }}
                </div>
            </div>

            <!-- Generation Form -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8">

                <form @submit.prevent="submit" class="space-y-8">

                    <!-- Prompt -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="font-semibold text-gray-800">
                                Describe your image
                                <span class="text-red-500">*</span>
                            </label>

                            <span class="text-xs text-gray-400">
                                {{ form.prompt.length }}/4000
                            </span>
                        </div>

                        <textarea v-model="form.prompt" maxlength="4000" rows="6"
                            placeholder="Describe the image you want to generate..."
                            class="w-full rounded-xl border border-gray-300 p-4 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none resize-y"
                            :class="{ 'border-red-500': form.errors.prompt }"></textarea>

                        <div class="flex justify-between mt-2">
                            <p v-if="form.errors.prompt" class="text-sm text-red-500">
                                {{ form.errors.prompt }}
                            </p>

                            <p v-else class="text-xs text-gray-400">
                                Be descriptive for better results.
                            </p>
                        </div>
                    </div>

                    <!-- Image Size -->
                    <div>
                        <label class="block font-semibold text-gray-800 mb-3">
                            Image Dimensions
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label v-for="item in [
                                    { value: '1024x1024', title: 'Square', ratio: '1:1' },
                                    { value: '1536x1024', title: 'Landscape', ratio: '3:2' },
                                    { value: '1024x1536', title: 'Portrait', ratio: '2:3' }
                                ]" :key="item.value" class="cursor-pointer">
                                <input type="radio" v-model="form.size" :value="item.value" class="sr-only peer" />

                                <div
                                    class="border-2 rounded-xl p-4 text-center transition peer-checked:border-blue-600 peer-checked:bg-blue-50 border-gray-200 hover:border-gray-400">
                                    <div class="flex justify-center mb-3">
                                        <div class="border-2 border-gray-400 rounded" :class="{
                                                'w-9 h-9': item.ratio === '1:1',
                                                'w-12 h-8': item.ratio === '3:2',
                                                'w-7 h-10': item.ratio === '2:3'
                                            }"></div>
                                    </div>

                                    <p class="font-semibold text-sm">
                                        {{ item.title }}
                                    </p>

                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ item.value }}
                                    </p>
                                </div>
                            </label>
                        </div>

                        <p v-if="form.errors.size" class="text-sm text-red-500 mt-2">
                            {{ form.errors.size }}
                        </p>
                    </div>

                    <!-- Quality -->
                    <div>
                        <label class="block font-semibold text-gray-800 mb-3">
                            Image Quality
                        </label>

                        <div class="grid grid-cols-3 gap-3">
                            <label v-for="quality in ['low', 'medium', 'high']" :key="quality" class="cursor-pointer">
                                <input type="radio" v-model="form.quality" :value="quality" class="sr-only peer" />

                                <div
                                    class="border-2 border-gray-200 rounded-xl p-3 text-center capitalize peer-checked:border-blue-600 peer-checked:bg-blue-50 hover:border-gray-400">
                                    <span class="font-semibold text-sm">
                                        {{ quality }}
                                    </span>

                                    <span v-if="quality === 'medium'" class="block text-xs text-blue-600 mt-1">
                                        Recommended
                                    </span>
                                </div>
                            </label>
                        </div>

                        <p v-if="form.errors.quality" class="text-sm text-red-500 mt-2">
                            {{ form.errors.quality }}
                        </p>
                    </div>

                    <!-- Reference Images -->
                    <div>
                        <div class="flex justify-between items-center mb-3">
                            <div>
                                <label class="font-semibold text-gray-800">
                                    Reference Images
                                </label>

                                <p class="text-xs text-gray-500 mt-1">
                                    Optional · Maximum 4 images
                                </p>
                            </div>

                            <button v-if="form.reference_images.length < maxImages" type="button" @click="addImage"
                                class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                                + Add URL
                            </button>
                        </div>

                        <div v-for="(url, index) in form.reference_images" :key="index" class="flex gap-2 mb-3">
                            <div class="flex-1">
                                <input v-model="form.reference_images[index]" type="url"
                                    placeholder="https://example.com/image.jpg"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none"
                                    :class="{
                                        'border-red-500':
                                            form.errors[`reference_images.${index}`]
                                    }" />

                                <p v-if="form.errors[`reference_images.${index}`]" class="text-xs text-red-500 mt-1">
                                    {{ form.errors[`reference_images.${index}`] }}
                                </p>
                            </div>

                            <button type="button" @click="removeImage(index)"
                                class="px-3 text-red-500 hover:bg-red-50 rounded-lg" title="Remove image URL">
                                ✕
                            </button>
                        </div>

                        <p v-if="form.errors.reference_images" class="text-sm text-red-500 mt-2">
                            {{ form.errors.reference_images }}
                        </p>

                        <div v-if="form.reference_images.length === 0"
                            class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center">
                            <p class="text-sm text-gray-500">
                                No reference images added.
                            </p>

                            <button type="button" @click="addImage" class="text-blue-600 text-sm font-semibold mt-2">
                                Add your first image URL
                            </button>
                        </div>
                    </div>

                    <!-- PRICING PREVIEW -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">

                        <div class="bg-gray-50 border-b border-gray-200 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">
                                        Generation Cost Estimate
                                    </h3>

                                    <p class="text-xs text-gray-500 mt-1">
                                        Estimated Pixelforge provider cost
                                    </p>
                                </div>

                                <span v-if="priceStatus === 'ready'"
                                    class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full">
                                    Calculated
                                </span>

                                <span v-else-if="priceStatus === 'loading'"
                                    class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full">
                                    Calculating...
                                </span>

                                <span v-else class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded-full">
                                    Unavailable
                                </span>
                            </div>
                        </div>

                        <div class="p-5">

                            <!-- Loading -->
                            <div v-if="priceStatus === 'loading'"
                                class="flex flex-col items-center justify-center py-8">
                                <div
                                    class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin">
                                </div>

                                <p class="text-sm text-gray-500 mt-4">
                                    Calculating your generation cost...
                                </p>
                            </div>

                            <!-- Error -->
                            <div v-else-if="priceStatus === 'error'"
                                class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <p class="font-semibold text-red-700">
                                    Pricing unavailable
                                </p>

                                <p class="text-sm text-red-600 mt-1">
                                    {{ priceError }}
                                </p>

                                <button type="button" @click="calculatePricing"
                                    class="text-sm font-semibold text-red-700 underline mt-3">
                                    Retry calculation
                                </button>
                            </div>

                            <!-- Pricing Result -->
                            <div v-else-if="priceStatus === 'ready' && pricePreview">

                                <!-- Estimated cost range -->
                                <div class="mb-6">

                                    <div class="bg-blue-50 rounded-xl p-4">
                                        <p class="text-xs text-blue-600">
                                            Maximum estimate (Without Markup)
                                        </p>

                                        <h2 class="text-2xl font-bold text-blue-700 mt-2">
                                            ${{ pricePreview.cost.maximum }}
                                        </h2>
                                    </div>

                                </div>

                                <!-- Calculation breakdown -->
                                <h4 class="font-semibold text-gray-800 mb-3">
                                    Token Calculation
                                </h4>

                                <div class="space-y-3 text-sm">

                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500">
                                            Prompt characters
                                        </span>

                                        <span class="font-medium">
                                            {{ pricePreview.inputs.prompt_characters }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500">
                                            Input text tokens
                                        </span>

                                        <span class="font-medium">
                                            {{ pricePreview.tokens.input_text.minimum.toLocaleString() }}
                                            -
                                            {{ pricePreview.tokens.input_text.maximum.toLocaleString() }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500">
                                            Reference images
                                            ({{ pricePreview.inputs.reference_images }})
                                        </span>

                                        <span class="font-medium">
                                            {{ pricePreview.tokens.input_image.estimated.toLocaleString() }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500">
                                            Output tokens
                                        </span>

                                        <span class="font-medium">
                                            {{ pricePreview.tokens.output.estimated.toLocaleString() }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500">
                                            Reasoning tokens
                                        </span>

                                        <span class="font-medium">
                                            {{ pricePreview.tokens.reasoning.minimum.toLocaleString() }}
                                            -
                                            {{ pricePreview.tokens.reasoning.maximum.toLocaleString() }}
                                        </span>
                                    </div>

                                    <div class="border-t border-gray-200 pt-3 flex justify-between">
                                        <span class="font-semibold">
                                            Currency
                                        </span>

                                        <span class="font-semibold uppercase">
                                            {{ pricePreview.currency }}
                                        </span>
                                    </div>

                                </div>

                                <p class="text-xs text-gray-400 mt-5 leading-relaxed">
                                    This is an estimated provider cost, not a guaranteed
                                    final charge. Actual usage may vary. Only successfully
                                    completed generations are billed by Pixelforge.
                                </p>

                            </div>

                        </div>
                    </div>

                    <!-- GENERATE BUTTON -->
                    <div class="pt-5 border-t border-gray-100">

                        <button type="submit" :disabled="form.processing || priceStatus !== 'ready' || !pricePreview"
                            class="w-full bg-blue-600 hover:bg-blue-700 hover:cursor-pointer disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold rounded-xl p-4 transition">
                            <span v-if="form.processing">
                                Submitting...
                            </span>

                            <span v-else-if="priceStatus === 'loading'">
                                Calculating Price...
                            </span>

                            <span v-else-if="priceStatus === 'error'">
                                Pricing Unavailable
                            </span>

                            <span v-else>
                                Generate Image
                            </span>
                        </button>

                        <p v-if="priceStatus !== 'ready'" class="text-center text-xs text-gray-500 mt-3">
                            Please wait until pricing is successfully calculated.
                        </p>

                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>