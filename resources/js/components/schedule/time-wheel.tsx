import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

const ITEM_HEIGHT = 44;
const VISIBLE_ITEMS = 5;

type Props = {
    /** "HH:MM" in 24 h format. */
    value: string;
    onChange: (value: string) => void;
    /** Minute step, 5 by default. */
    step?: number;
    label: string;
};

/**
 * Alarm-clock style time picker: scroll-snapping wheels for hour (1–12),
 * minutes and a. m. / p. m. Works with touch, mouse wheel and keyboard
 * (arrow keys on a focused wheel). Value is always 24 h "HH:MM".
 */
export function TimeWheel({ value, onChange, step = 5, label }: Props) {
    const [hours24, minutes] = value.split(':').map(Number);
    const period = hours24 >= 12 ? 'pm' : 'am';
    const hours12 = hours24 % 12 === 0 ? 12 : hours24 % 12;

    const emit = (h12: number, m: number, p: 'am' | 'pm') => {
        const h24 = (h12 % 12) + (p === 'pm' ? 12 : 0);
        onChange(
            `${String(h24).padStart(2, '0')}:${String(m).padStart(2, '0')}`,
        );
    };

    const hourOptions = Array.from({ length: 12 }, (_, i) => i + 1);
    const minuteOptions = Array.from({ length: 60 / step }, (_, i) => i * step);

    return (
        <div
            role="group"
            aria-label={label}
            className="flex items-center justify-center gap-1 rounded-2xl border bg-card px-2"
        >
            <Wheel
                label="Hora"
                options={hourOptions}
                value={hours12}
                format={(h) => String(h)}
                onChange={(h) => emit(h, minutes, period)}
            />
            <span
                className="pb-0.5 text-2xl font-semibold text-muted-foreground"
                aria-hidden
            >
                :
            </span>
            <Wheel
                label="Minutos"
                options={minuteOptions}
                value={minuteOptions.includes(minutes) ? minutes : 0}
                format={(m) => String(m).padStart(2, '0')}
                onChange={(m) => emit(hours12, m, period)}
            />
            <Wheel
                label="Mañana o tarde"
                options={['am', 'pm'] as const}
                value={period}
                format={(p) => (p === 'am' ? 'a. m.' : 'p. m.')}
                onChange={(p) => emit(hours12, minutes, p)}
                narrow
            />
        </div>
    );
}

type WheelProps<T extends string | number> = {
    label: string;
    options: readonly T[];
    value: T;
    format: (option: T) => string;
    onChange: (option: T) => void;
    narrow?: boolean;
};

function Wheel<T extends string | number>({
    label,
    options,
    value,
    format,
    onChange,
    narrow = false,
}: WheelProps<T>) {
    const ref = useRef<HTMLDivElement>(null);
    const settleTimer = useRef<number | undefined>(undefined);
    const index = Math.max(0, options.indexOf(value));

    // Keep the scroll position in sync when the value changes from outside.
    useEffect(() => {
        const element = ref.current;

        if (element && Math.round(element.scrollTop / ITEM_HEIGHT) !== index) {
            element.scrollTo({ top: index * ITEM_HEIGHT });
        }
    }, [index]);

    const onScroll = () => {
        window.clearTimeout(settleTimer.current);
        // Commit once the snap animation settles, not on every pixel.
        settleTimer.current = window.setTimeout(() => {
            const element = ref.current;

            if (!element) {
                return;
            }

            const next = Math.min(
                options.length - 1,
                Math.max(0, Math.round(element.scrollTop / ITEM_HEIGHT)),
            );

            if (next !== index) {
                onChange(options[next]);
            }
        }, 120);
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        const last = options.length - 1;
        const target: Record<string, number> = {
            ArrowDown: index + 1,
            ArrowUp: index - 1,
            PageDown: index + 3,
            PageUp: index - 3,
            Home: 0,
            End: last,
        };

        if (event.key in target) {
            event.preventDefault();
            // Clamped like the touch gesture: the wheel does not wrap around.
            onChange(options[Math.min(last, Math.max(0, target[event.key]))]);
        }
    };

    const padding = ((VISIBLE_ITEMS - 1) / 2) * ITEM_HEIGHT;

    return (
        <div className="relative">
            <div
                className="pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 rounded-lg bg-primary/10"
                style={{ height: ITEM_HEIGHT }}
                aria-hidden
            />
            <div
                ref={ref}
                role="spinbutton"
                tabIndex={0}
                aria-label={label}
                aria-valuetext={format(value)}
                aria-valuenow={typeof value === 'number' ? value : index}
                aria-valuemin={
                    typeof value === 'number' ? Number(options[0]) : 0
                }
                aria-valuemax={
                    typeof value === 'number'
                        ? Number(options[options.length - 1])
                        : options.length - 1
                }
                onScroll={onScroll}
                onKeyDown={onKeyDown}
                className={cn(
                    'relative snap-y snap-mandatory [scrollbar-width:none] overflow-y-scroll overscroll-contain focus-visible:rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-scrollbar]:hidden',
                    narrow ? 'w-16' : 'w-14',
                )}
                style={{
                    height: ITEM_HEIGHT * VISIBLE_ITEMS,
                    paddingBlock: padding,
                }}
            >
                {options.map((option, i) => (
                    <button
                        key={String(option)}
                        type="button"
                        tabIndex={-1}
                        // The wheel itself is the control; options are a visual aid.
                        aria-hidden
                        onClick={() => onChange(option)}
                        className={cn(
                            'flex w-full snap-center items-center justify-center text-lg tabular-nums transition-colors',
                            i === index
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground',
                        )}
                        style={{ height: ITEM_HEIGHT }}
                    >
                        {format(option)}
                    </button>
                ))}
            </div>
        </div>
    );
}
