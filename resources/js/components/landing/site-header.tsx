import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';
import { BrandMark } from './brand-mark';
import { AUTH_LABELS, NAV_ITEMS } from './content';
import { CONTAINER_CLASS } from './landing-section';
import { MobileMenu } from './mobile-menu';

const SCROLLED_THRESHOLD_PX = 8;

type Props = {
    isAuthenticated: boolean;
};

function useIsScrolled(): boolean {
    const [isScrolled, setIsScrolled] = useState(false);

    useEffect(() => {
        const update = () =>
            setIsScrolled(window.scrollY > SCROLLED_THRESHOLD_PX);
        update();
        window.addEventListener('scroll', update, { passive: true });

        return () => window.removeEventListener('scroll', update);
    }, []);

    return isScrolled;
}

export function SiteHeader({ isAuthenticated }: Props) {
    const isScrolled = useIsScrolled();

    return (
        <header
            className={cn(
                'sticky top-0 z-40 border-b transition-colors duration-200',
                isScrolled
                    ? 'border-border bg-background/90 backdrop-blur'
                    : 'border-transparent bg-background',
            )}
        >
            <div
                className={cn(CONTAINER_CLASS, 'flex h-16 items-center gap-6')}
            >
                <a
                    href="#inicio"
                    className="flex min-h-11 items-center rounded-md"
                >
                    <BrandMark />
                </a>

                <nav
                    aria-label="Principal"
                    className="hidden flex-1 justify-center lg:flex"
                >
                    <ul className="flex items-center gap-1">
                        {NAV_ITEMS.map((item) => (
                            <li key={item.href}>
                                <a
                                    href={item.href}
                                    className="inline-flex h-11 items-center rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    {item.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="ml-auto flex items-center gap-2">
                    {isAuthenticated ? (
                        <Link
                            href={dashboard()}
                            className={cn(
                                buttonVariants(),
                                'rounded-full px-5',
                            )}
                        >
                            {AUTH_LABELS.dashboard}
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={login()}
                                className={cn(
                                    buttonVariants({ variant: 'ghost' }),
                                    'hidden md:inline-flex',
                                )}
                            >
                                {AUTH_LABELS.login}
                            </Link>
                            <Link
                                href={register()}
                                className={cn(
                                    buttonVariants(),
                                    'rounded-full px-5',
                                )}
                            >
                                {AUTH_LABELS.register}
                            </Link>
                        </>
                    )}
                    <MobileMenu isAuthenticated={isAuthenticated} />
                </div>
            </div>
        </header>
    );
}
