import { Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { HERO } from './content';
import { CONTAINER_CLASS } from './landing-section';
import { PhoneMockup } from './phone-mockup';

const EASE_OUT = [0.22, 1, 0.36, 1] as const;

export function Hero() {
    const reduceMotion = useReducedMotion();
    const enter = (delay: number) => ({
        initial: { opacity: 0, y: reduceMotion ? 0 : 16 },
        animate: { opacity: 1, y: 0 },
        transition: {
            duration: reduceMotion ? 0.15 : 0.4,
            delay: reduceMotion ? 0 : delay,
            ease: EASE_OUT,
        },
    });

    return (
        <section id="inicio" className="relative scroll-mt-20 overflow-hidden">
            {/* Decorative glow in the brand color; purely visual */}
            <div
                aria-hidden
                className="pointer-events-none absolute top-16 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-primary/15 blur-3xl md:left-3/4"
            />

            <div
                className={cn(
                    CONTAINER_CLASS,
                    'relative grid items-center gap-14 pt-10 pb-20 md:pt-16 md:pb-24 lg:grid-cols-[1.15fr_1fr]',
                )}
            >
                <div className="flex flex-col items-start gap-6">
                    <motion.p
                        {...enter(0)}
                        className="rounded-full border bg-card px-3 py-1 text-sm font-medium text-muted-foreground"
                    >
                        {HERO.eyebrow}
                    </motion.p>
                    <motion.h1
                        {...enter(0.05)}
                        className="font-display text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                    >
                        {HERO.title}
                    </motion.h1>
                    <motion.p
                        {...enter(0.1)}
                        className="max-w-xl text-lg text-pretty text-muted-foreground"
                    >
                        {HERO.subtitle}
                    </motion.p>

                    <motion.div
                        {...enter(0.15)}
                        className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row"
                    >
                        <Link
                            href={register()}
                            className={cn(
                                buttonVariants({ size: 'lg' }),
                                'rounded-full px-7 md:h-12',
                            )}
                        >
                            {HERO.primaryCta}
                            <ArrowRight aria-hidden />
                        </Link>
                        <a
                            href={HERO.secondaryHref}
                            className={cn(
                                buttonVariants({
                                    variant: 'outline',
                                    size: 'lg',
                                }),
                                'rounded-full px-7 md:h-12',
                            )}
                        >
                            {HERO.secondaryCta}
                        </a>
                    </motion.div>

                    <motion.ul
                        {...enter(0.2)}
                        className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground"
                    >
                        {HERO.trust.map((item) => (
                            <li
                                key={item}
                                className="flex items-center gap-1.5"
                            >
                                <Check
                                    className="size-4 text-primary"
                                    aria-hidden
                                />
                                {item}
                            </li>
                        ))}
                    </motion.ul>
                </div>

                <motion.div
                    {...enter(0.2)}
                    className="flex justify-center lg:justify-end"
                >
                    <PhoneMockup />
                </motion.div>
            </div>
        </section>
    );
}
