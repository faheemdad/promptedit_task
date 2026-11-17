/**
 * Every amount from the backend is an integer of nano-USD (1 USD = 1e9 nanos),
 * so the browser never does float arithmetic on money. These helpers only format.
 */
const NANOS_PER_USD = 1_000_000_000;

/** Rounded for display, e.g. "$0.0743". Non-zero amounts that would round to zero show as "<$0.0001". */
export function formatUsd(nanos: number, decimals = 4): string {
    const unit = 10 ** (9 - decimals);
    const rounded = Math.round(Math.abs(nanos) / unit);
    const sign = nanos < 0 ? '-' : '';

    if (rounded === 0 && nanos !== 0) {
        return `${sign}<$${(1 / 10 ** decimals).toFixed(decimals)}`;
    }

    return `${sign}$${(rounded / 10 ** decimals).toFixed(decimals)}`;
}

/** The exact amount, e.g. "$0.07434375" (trailing zeros trimmed, at least 2 decimals). */
export function formatUsdExact(nanos: number): string {
    const sign = nanos < 0 ? '-' : '';
    const abs = Math.abs(nanos);
    const whole = Math.floor(abs / NANOS_PER_USD);
    const fraction = String(abs % NANOS_PER_USD)
        .padStart(9, '0')
        .replace(/0+$/, '')
        .padEnd(2, '0');

    return `${sign}$${whole}.${fraction}`;
}
