import { ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { isValidReferenceImageUrl } from '../utils/generationValidation';

export function useGenerationPricing(form, maxImages = 4) {
    const priceStatus = ref('idle');
    const pricePreview = ref(null);
    const priceError = ref('');

    let debounceTimer = null;
    let abortController = null;
    let requestVersion = 0;

    const DEBOUNCE_DELAY = 1000;

    function showPricingError(message) {
        pricePreview.value = null;
        priceStatus.value = 'error';
        priceError.value = message;
    }

    async function calculatePricing() {
        clearTimeout(debounceTimer);

        // Cancel previous request
        abortController?.abort();
        abortController = null;

        const currentVersion = ++requestVersion;

        // Invalidate previous pricing
        pricePreview.value = null;
        priceError.value = '';

        // Validate prompt
        if (!form.prompt.trim()) {
            priceStatus.value = 'idle';
            return;
        }

        if (form.prompt.length > 4000) {
            showPricingError('Prompt cannot exceed 4,000 characters.');
            return;
        }

        if (form.reference_images.length > maxImages) {
            showPricingError('Maximum 4 reference images allowed.');
            return;
        }

        const referenceImages = form.reference_images
            .map(url => url.trim())
            .filter(Boolean);

        for (const url of referenceImages) {
            if (!isValidReferenceImageUrl(url)) {
                showPricingError(
                    'Please enter valid HTTP or HTTPS reference image URLs.'
                );
                return;
            }
        }

        const controller = new AbortController();
        abortController = controller;

        priceStatus.value = 'loading';

        try {
            const response = await axios.post(
                '/generations/pricing',
                {
                    prompt: form.prompt,
                    size: form.size,
                    quality: form.quality,
                    reference_images: referenceImages,
                },
                {
                    signal: controller.signal,
                }
            );

            // Ignore outdated responses
            if (currentVersion !== requestVersion) {
                return;
            }

            if (!response.data.success || !response.data.pricing) {
                throw new Error('Pricing calculation failed.');
            }

            pricePreview.value = response.data.pricing;
            priceStatus.value = 'ready';

        } catch (error) {
            if (
                error.code === 'ERR_CANCELED' ||
                error.name === 'CanceledError'
            ) {
                return;
            }

            if (currentVersion !== requestVersion) {
                return;
            }

            showPricingError(
                error.response?.data?.message ||
                error.message ||
                'Unable to calculate pricing. Please try again.'
            );

        } finally {
            if (currentVersion === requestVersion) {
                abortController = null;
            }
        }
    }

    function schedulePricing() {
        clearTimeout(debounceTimer);

        // Cancel previous request immediately
        abortController?.abort();
        abortController = null;

        // Invalidate previous request
        requestVersion++;

        // Clear outdated pricing
        pricePreview.value = null;
        priceError.value = '';

        if (!form.prompt.trim()) {
            priceStatus.value = 'idle';
            return;
        }

        // Wait until the user finishes typing
        debounceTimer = setTimeout(() => {
            calculatePricing();
        }, DEBOUNCE_DELAY);
    }

    // Watch all pricing-related fields
    watch(
        () => [
            form.prompt,
            form.size,
            form.quality,
            form.reference_images.join('|'),
        ],
        schedulePricing,
        { immediate: true }
    );

    // Cleanup
    onBeforeUnmount(() => {
        clearTimeout(debounceTimer);
        abortController?.abort();
        requestVersion++;
    });

    return {
        priceStatus,
        pricePreview,
        priceError,
        calculatePricing: schedulePricing,
    };
}
