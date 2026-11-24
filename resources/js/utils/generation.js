
/**
 * Format USD costs without losing small decimal values.
 *
 * Examples:
 * 0.00354545 -> $0.00354545
 * 1.50000000 -> $1.50
 * 10.00000000 -> $10.00
 */
export const formatCost = (cost) => {
    if (cost === null || cost === undefined || cost === '') {
        return 'Pending';
    }

    const [whole, fraction = ''] = String(cost).split('.');

    const trimmedFraction = fraction.replace(/0+$/, '');

    const decimals = trimmedFraction.length < 2
        ? fraction.slice(0, 2).padEnd(2, '0')
        : trimmedFraction;

    return `$${whole}.${decimals}`;
};

/**
 * Return CSS classes for generation status.
 */
export const statusClass = (status) => {
    const classes = {
        queued: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        succeeded: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
        rejected: 'bg-red-100 text-red-800',
    };

    return classes[status] || 'bg-gray-100 text-gray-700';
};

/**
 * Return readable generation status.
 */
export const statusLabel = (status) => {
    const labels = {
        queued: 'Queued',
        processing: 'Processing',
        succeeded: 'Succeeded',
        failed: 'Failed',
        rejected: 'Rejected',
    };

    return labels[status] || status;
};
