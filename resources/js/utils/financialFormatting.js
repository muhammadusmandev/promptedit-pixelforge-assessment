
export const money = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 9,
    }).format(Number(value || 0));
};

export const formatDate = (date) => {
    if (!date) return '-';

    return new Date(date + 'T00:00:00').toLocaleDateString(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }
    );
};
