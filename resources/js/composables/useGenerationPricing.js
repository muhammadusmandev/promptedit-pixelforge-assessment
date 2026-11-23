
import { ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { isValidReferenceImageUrl } from '../utils/generationValidation';

export function useGenerationPricing(form, maxImages = 4) {
    const priceStatus = ref('loading');
    const pricePreview = ref(null);
    const priceError = ref('');

    let debounceTimer = null;
    let abortController = null;
    let requestVersion = 0;

    function showPricingError(message) {
        pricePreview.value = null;
        priceStatus.value = 'error';
        priceError.value = message;
    }

    function calculatePricing() {
        clearTimeout(debounceTimer);

        if (abortController) {
            abortController.abort();
        }

        const currentVersion = ++requestVersion;

        pricePreview.value = null;
        priceError.value = '';
        priceStatus.value = 'loading';

        debounceTimer = setTimeout(async () => {
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

            abortController = new AbortController();

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
                        signal: abortController.signal,
                    }
                );

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
                    'Unable to calculate pricing. Please try again.'
                );
            }
        }, 400);
    }

    watch(
        () => [
            form.prompt,
            form.size,
            form.quality,
            form.reference_images.join('|'),
        ],
        calculatePricing,
        { immediate: true }
    );

    onBeforeUnmount(() => {
        clearTimeout(debounceTimer);
        abortController?.abort();
    });

    return {
        priceStatus,
        pricePreview,
        priceError,
        calculatePricing,
    };
}
