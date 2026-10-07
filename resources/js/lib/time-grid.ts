const MINUTES_PER_DAY = 24 * 60;

/** "15:30" → 930 (minutes since midnight). */
export function toMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

/** 930 → "15:30". */
export function toTime(minutes: number): string {
    const hours = Math.floor(minutes / 60);

    return `${String(hours).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

export function snap(minutes: number, step: number): number {
    return Math.round(minutes / step) * step;
}

/** End of a visit in minutes of its own day; one that crosses midnight ends at 24:00. */
export function endMinutes(time: string, endTime: string): number {
    const end = toMinutes(endTime);

    return end <= toMinutes(time) ? MINUTES_PER_DAY : end;
}

export type Span<T> = { item: T; start: number; end: number };
export type Placed<T> = Span<T> & {
    /** Position among the visits it shares time with, from the left. */
    lane: number;
    /** How many share that stretch: the width is divided by it. */
    lanes: number;
};

/**
 * Lays out the visits of one column side by side where they share time
 * (two professionals at once, or a cut during a color's processing time).
 */
export function placeInLanes<T>(spans: Span<T>[]): Placed<T>[] {
    const sorted = [...spans].sort(
        (a, b) => a.start - b.start || a.end - b.end,
    );
    const placed: Placed<T>[] = [];
    let cluster: Placed<T>[] = [];
    let clusterEnd = 0;

    const close = () => {
        const lanes = Math.max(0, ...cluster.map((span) => span.lane)) + 1;

        cluster.forEach((span) => placed.push({ ...span, lanes }));
        cluster = [];
        clusterEnd = 0;
    };

    for (const span of sorted) {
        if (cluster.length > 0 && span.start >= clusterEnd) {
            close();
        }

        const taken = cluster
            .filter((other) => other.end > span.start)
            .map((other) => other.lane);
        let lane = 0;

        while (taken.includes(lane)) {
            lane++;
        }

        cluster.push({ ...span, lane, lanes: 1 });
        clusterEnd = Math.max(clusterEnd, span.end);
    }

    close();

    return placed;
}
