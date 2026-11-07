export function cn(...classes) {
    return classes.filter(Boolean).join(' ');
}

export function formatMoney(value, currency = 'USD', maximumFractionDigits = 0) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits,
        minimumFractionDigits: maximumFractionDigits,
    }).format(Number(value) || 0);
}

export function formatMoneyPrecise(value, currency = 'USD') {
    return formatMoney(value, currency, 2);
}

export function formatNumber(value) {
    return new Intl.NumberFormat('en-US').format(Number(value) || 0);
}

export function formatDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export function initials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

export function percentage(value) {
    return `${Math.round((Number(value) || 0) * 10) / 10}%`;
}
