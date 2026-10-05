import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';
import { AUTH_LABELS, NAV_ITEMS } from './content';

type Props = {
    isAuthenticated: boolean;
};

/** Navigation for screens below `lg`, where the inline links do not fit. */
export function MobileMenu({ isAuthenticated }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const close = () => setIsOpen(false);

    return (
        <Sheet open={isOpen} onOpenChange={setIsOpen}>
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-11 lg:hidden"
                    aria-label={AUTH_LABELS.openMenu}
                >
                    <Menu className="size-5" aria-hidden />
                </Button>
            </SheetTrigger>
            <SheetContent
                side="right"
                className="w-[85vw] max-w-xs gap-0 p-0 [&>button:last-child]:top-3 [&>button:last-child]:right-3 [&>button:last-child]:flex [&>button:last-child]:size-11 [&>button:last-child]:items-center [&>button:last-child]:justify-center [&>button:last-child]:rounded-md"
            >
                <SheetHeader className="border-b px-6 py-5">
                    <SheetTitle className="font-display text-lg">
                        {AUTH_LABELS.menuTitle}
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        Secciones de la página
                    </SheetDescription>
                </SheetHeader>

                <nav aria-label="Principal" className="px-3 py-3">
                    <ul className="flex flex-col">
                        {NAV_ITEMS.map((item) => (
                            <li key={item.href}>
                                <a
                                    href={item.href}
                                    onClick={close}
                                    className="flex min-h-12 items-center rounded-md px-3 text-base font-medium transition-colors hover:bg-accent"
                                >
                                    {item.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="mt-auto flex flex-col gap-3 border-t p-6">
                    {isAuthenticated ? (
                        <Link
                            href={dashboard()}
                            className={cn(
                                buttonVariants({ size: 'lg' }),
                                'rounded-full',
                            )}
                        >
                            {AUTH_LABELS.dashboard}
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={register()}
                                className={cn(
                                    buttonVariants({ size: 'lg' }),
                                    'rounded-full',
                                )}
                            >
                                {AUTH_LABELS.register}
                            </Link>
                            <Link
                                href={login()}
                                className={cn(
                                    buttonVariants({
                                        variant: 'outline',
                                        size: 'lg',
                                    }),
                                    'rounded-full',
                                )}
                            >
                                {AUTH_LABELS.login}
                            </Link>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
