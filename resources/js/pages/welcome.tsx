import { Head, usePage } from '@inertiajs/react';
import { useReducedMotion } from 'motion/react';
import { useEffect } from 'react';
import { About } from '@/components/landing/about';
import { Audiences } from '@/components/landing/audiences';
import { BeforeAfter } from '@/components/landing/before-after';
import { META_DESCRIPTION, PAGE_TITLE } from '@/components/landing/content';
import { Faq } from '@/components/landing/faq';
import { Features } from '@/components/landing/features';
import { FinalCta } from '@/components/landing/final-cta';
import { Hero } from '@/components/landing/hero';
import { HowItWorks } from '@/components/landing/how-it-works';
import { Pricing } from '@/components/landing/pricing';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';

/** Smooth anchor scrolling only while this page is mounted and motion is allowed. */
function useSmoothScroll(): void {
    const reduceMotion = useReducedMotion();

    useEffect(() => {
        if (reduceMotion) {
            return;
        }

        const root = document.documentElement;
        root.style.scrollBehavior = 'smooth';

        return () => {
            root.style.scrollBehavior = '';
        };
    }, [reduceMotion]);
}

export default function Welcome() {
    const { auth } = usePage().props;
    useSmoothScroll();

    return (
        <>
            <Head title={PAGE_TITLE}>
                <meta
                    head-key="description"
                    name="description"
                    content={META_DESCRIPTION}
                />
            </Head>

            <div className="min-h-svh overflow-x-clip bg-background text-foreground">
                <SiteHeader isAuthenticated={Boolean(auth.user)} />
                <main>
                    <Hero />
                    <BeforeAfter />
                    <Features />
                    <HowItWorks />
                    <Audiences />
                    <Pricing />
                    <Faq />
                    <About />
                    <FinalCta />
                </main>
                <SiteFooter />
            </div>
        </>
    );
}
