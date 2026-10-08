import { Check } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';
import { BrandTheme } from '@/components/salon/brand-theme';
import { SALON_THEMES } from '@/components/salon/salon-themes';
import { MiniSalonPage } from '@/components/theme-preview/mini-salon-page';
import { PhoneFrame } from '@/components/theme-preview/phone-frame';
import type { SalonTheme } from '@/types';
import { SHOWCASE, SHOWCASE_SALON } from './content';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';
import { TemplatePicker } from './template-picker';

/**
 * "Así se ve tu página": a phone with the page of an example salon, in the
 * template the visitor picks. Each template keeps its own accent color
 * (no brand), the way a salon looks before choosing its color.
 */
export function TemplateShowcase() {
    const [theme, setTheme] = useState<SalonTheme>('nocturna');
    const reduceMotion = useReducedMotion();

    return (
        <LandingSection id="tu-pagina" tone="muted" className="overflow-hidden">
            {/* Phones: text, picker, phone, bullets. Desktop: text and bullets left, phone right. */}
            <div className="grid items-center gap-10 lg:grid-cols-[1fr_auto] lg:gap-x-20 lg:gap-y-8">
                <div className="flex flex-col gap-8 lg:self-end">
                    <SectionHeading
                        eyebrow={SHOWCASE.eyebrow}
                        title={SHOWCASE.title}
                        description={SHOWCASE.description}
                        align="start"
                    />
                    <Reveal className="flex flex-col gap-3">
                        <TemplatePicker value={theme} onChange={setTheme} />
                        <p
                            aria-live="polite"
                            className="min-h-12 text-base text-pretty text-muted-foreground"
                        >
                            <span className="font-semibold text-foreground">
                                {SALON_THEMES[theme].label}:
                            </span>{' '}
                            {SALON_THEMES[theme].description}
                        </p>
                    </Reveal>
                </div>

                <Reveal className="lg:col-start-1 lg:self-start">
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {SHOWCASE.bullets.map((bullet) => (
                            <li
                                key={bullet}
                                className="flex items-start gap-3 text-base font-medium"
                            >
                                <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                    <Check
                                        className="size-3.5"
                                        strokeWidth={2.5}
                                        aria-hidden
                                    />
                                </span>
                                {bullet}
                            </li>
                        ))}
                    </ul>
                </Reveal>

                <Reveal className="relative row-start-2 mx-auto w-[17.5rem] sm:w-[19rem] lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:w-[20rem]">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -inset-16 rounded-full bg-primary/15 blur-3xl"
                    />
                    <PhoneFrame>
                        <AnimatePresence initial={false}>
                            <motion.div
                                key={theme}
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                transition={{
                                    duration: reduceMotion ? 0.1 : 0.25,
                                }}
                                className="absolute inset-0"
                            >
                                <BrandTheme
                                    theme={theme}
                                    brand={null}
                                    className="size-full"
                                >
                                    <MiniSalonPage
                                        salon={SHOWCASE_SALON}
                                        className="size-full"
                                    />
                                </BrandTheme>
                            </motion.div>
                        </AnimatePresence>
                    </PhoneFrame>
                </Reveal>
            </div>
        </LandingSection>
    );
}
