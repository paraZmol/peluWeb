import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { usePortalTheme } from '@/components/salon/portal-theme';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SHEET_ON_PHONE, TOUCH_FOOTER } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    triggerLabel: string;
    title: string;
    description: string;
    confirmLabel: string;
    form: RouteFormDefinition<'post'>;
    /** A button of its own instead of the quiet red one. */
    trigger?: ReactNode;
    /** "default" for confirmations that destroy nothing (e.g. money received). */
    tone?: 'destructive' | 'default';
    /** Inside a salon's template (client pages): the dialog wears it too. */
    themed?: boolean;
};

/**
 * Asks before doing something that cannot be undone with one tap. The way
 * out is always "Volver": "Cancelar" would be ambiguous when the question
 * itself is about cancelling.
 */
export function ConfirmRemoveDialog({
    triggerLabel,
    title,
    description,
    confirmLabel,
    form,
    trigger,
    tone = 'destructive',
    themed = false,
}: Props) {
    const portalTheme = usePortalTheme();
    const content = (
        <>
            <DialogHeader>
                {/* Centered on a phone: clear of the close button on both sides. */}
                <DialogTitle className="px-5 leading-snug text-balance sm:pr-6 sm:pl-0">
                    {title}
                </DialogTitle>
                <DialogDescription>{description}</DialogDescription>
            </DialogHeader>
            <Form {...form}>
                {({ processing }) => (
                    <DialogFooter className={TOUCH_FOOTER}>
                        <DialogClose asChild>
                            <Button variant="outline">Volver</Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant={tone}
                            disabled={processing}
                        >
                            {confirmLabel}
                        </Button>
                    </DialogFooter>
                )}
            </Form>
        </>
    );

    return (
        <Dialog>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive-foreground"
                    >
                        {triggerLabel}
                    </Button>
                )}
            </DialogTrigger>
            {themed ? (
                <DialogContent
                    {...portalTheme}
                    className={cn(portalTheme.className, SHEET_ON_PHONE)}
                >
                    {content}
                </DialogContent>
            ) : (
                <DialogContent className={SHEET_ON_PHONE}>
                    {content}
                </DialogContent>
            )}
        </Dialog>
    );
}
