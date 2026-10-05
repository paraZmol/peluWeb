import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { FINAL_CTA } from './content';
import { CONTAINER_CLASS } from './landing-section';
import { Reveal } from './reveal';

export function FinalCta() {
    return (
        <section className="py-16 md:py-24">
            <div className={CONTAINER_CLASS}>
                <Reveal className="rounded-[2rem] bg-primary px-6 py-14 text-center text-primary-foreground md:px-12 md:py-20">
                    <div className="mx-auto flex max-w-2xl flex-col items-center gap-5">
                        <h2 className="font-display text-3xl font-semibold tracking-tight text-balance md:text-5xl">
                            {FINAL_CTA.title}
                        </h2>
                        <p className="text-base text-pretty text-primary-foreground/85 md:text-lg">
                            {FINAL_CTA.description}
                        </p>
                        <Link
                            href={register()}
                            className={cn(
                                buttonVariants({ size: 'lg' }),
                                'mt-2 w-full rounded-full bg-primary-foreground px-8 text-primary hover:bg-primary-foreground/90 sm:w-auto md:h-12',
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
