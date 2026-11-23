export function isValidReferenceImageUrl(url) {
    try {
        const parsed = new URL(url);

        return ['http:', 'https:'].includes(parsed.protocol);
    } catch {
        return false;
    }
}
