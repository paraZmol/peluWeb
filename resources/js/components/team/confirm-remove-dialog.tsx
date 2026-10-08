import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
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
import { SHEET_ON_PHONE } from '@/lib/sheet';
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
}: Props) {
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
            <DialogContent className={SHEET_ON_PHONE}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Form {...form}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2 [&_button]:h-11 sm:[&_button]:h-9">
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
            </DialogContent>
        </Dialog>
    );
}
