/** The one question of each step of the public wizard. */
export const STEP_TITLES = [
    '¿Qué te quieres hacer?',
    '¿Con quién?',
    '¿Cuándo?',
    'Revisa y confirma',
];

/** A line under the question; the time step names who the times are for. */
export function stepSubtitle(
    step: number,
    staffName: string | null,
): string | null {
    return [
        'Elige uno o varios servicios: los puedes combinar.',
        'Elige a quien te atiende.',
        staffName
            ? `Elige el día y la hora. Solo ves los horarios libres de ${staffName}.`
            : 'Elige el día y la hora.',
        null,
    ][step];
}
