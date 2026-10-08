import { ChevronDown } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { FAQ_HEADING, FAQS } from './content';
import type { Faq as FaqEntry } from './content';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

/** Radix sets aria-expanded on the trigger and data-state, which drives the chevron. */
function FaqItem({ faq }: { faq: FaqEntry }) {
    return (
        <Collapsible className="group border-b last:border-b-0">
            <CollapsibleTrigger className="flex min-h-14 w-full items-center justify-between gap-4 rounded-md py-4 text-left text-base font-semibold outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-lg">
                {faq.question}
                <ChevronDown
                    className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180"
                    aria-hidden
                />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <p className="pr-9 pb-5 text-base text-pretty text-muted-foreground">
                    {faq.answer}
                </p>
            </CollapsibleContent>
        </Collapsible>
    );
}

export function Faq() {
    return (
        <LandingSection id="preguntas">
            <SectionHeading {...FAQ_HEADING} />
            <Reveal className="mx-auto mt-12 max-w-3xl rounded-3xl px-5 skin-card md:px-8">
                {FAQS.map((faq) => (
                    <FaqItem key={faq.question} faq={faq} />
                ))}
            </Reveal>
        </LandingSection>
    );
}
