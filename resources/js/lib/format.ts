/** "35.50" → "S/ 35.50". Amounts arrive as text from the server (no floats). */
export function formatPrice(amount: string): string {
    return `S/ ${amount}`;
}

/** 45 → "45 min", 60 → "1 h", 90 → "1 h 30 min". */
export function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}
