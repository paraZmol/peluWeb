import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { FINAL_CTA } from './content';
import { CONTAINER_CLASS } from './landing-section';
import { Reveal } from './reveal';

/** Closing call to action over a real salon photo, with a veil for the text. */
export function FinalCta() {
    return (
        <section className="py-16 md:py-24">
            <div className={CONTAINER_CLASS}>
                <Reveal className="photo-frame rounded-3xl shadow-float">
                    <img
                        src={FINAL_CTA.photo.src}
                        alt={FINAL_CTA.photo.alt}
                        loading="lazy"
                        className="absolute inset-0"
                    />
                    <div aria-hidden className="absolute inset-0 photo-veil" />
                    <div
                        aria-hidden
                        className="absolute inset-0 hidden bg-linear-to-r from-veil/90 via-veil/55 to-transparent md:block"
                    />
                    <div className="relative z-10 flex min-h-[26rem] max-w-2xl flex-col items-start justify-end gap-5 px-6 py-10 text-on-photo md:min-h-[30rem] md:justify-center md:px-14 md:py-16">
                        <h2 className="font-display text-4xl font-semibold tracking-tight text-balance md:text-5xl">
                            {FINAL_CTA.title}
                        </h2>
                        <p className="text-lg text-pretty text-on-photo/85">
                            {FINAL_CTA.description}
                        </p>
                        <Link
                            href={register()}
                            className={cn(
                                buttonVariants({ size: 'lg' }),
                                'mt-2 h-12 w-full rounded-full px-8 shadow-brand sm:w-auto',
                            )}
                        >
                            {FINAL_CTA.cta}
                            <ArrowRight aria-hidden />
                        </Link>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
