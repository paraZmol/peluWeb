import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SHEET_ON_PHONE, TOUCH_BUTTON } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { AuthorizeForm } from './authorize-form';
import type { ConsentKind } from './types';

type Props = {
    clientId: number;
    kind: ConsentKind;
    /** What opens it, e.g. "Autorizar". */
    triggerLabel: string;
    /** The same, naming the salon: several cards repeat the button. */
    triggerName: string;
    title: string;
    /** What the person agrees to, word for word: shown whole, never folded. */
    text: string;
    /** The sentence beside the box they tick. */
    acceptLabel: string;
};

/**
 * The person gives an authorization from their own account: the whole text
 * in front of them and a box that always starts unticked. A sheet from the
 * bottom on a phone.
 */
export function AuthorizeDialog({
    clientId,
    kind,
    triggerLabel,
    triggerName,
    title,
    text,
    acceptLabel,
}: Props) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button
                    aria-label={triggerName}
                    className={cn(TOUCH_BUTTON, 'sm:self-start')}
                >
                    {triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent className={cn(SHEET_ON_PHONE, 'overflow-y-auto')}>
                <DialogHeader className="gap-3 text-left">
                    <DialogTitle className="pr-8 leading-snug text-balance">
                        {title}
                    </DialogTitle>
                    {/* The document, set apart from what is asked below it. */}
                    <DialogDescription className="rounded-lg border bg-surface-2 p-3 leading-relaxed text-foreground sm:p-4">
                        {text}
                    </DialogDescription>
                </DialogHeader>
                {/* Lives only while open: every time it starts unticked. */}
                <AuthorizeForm
                    clientId={clientId}
                    kind={kind}
                    acceptLabel={acceptLabel}
                    onDone={() => setIsOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
