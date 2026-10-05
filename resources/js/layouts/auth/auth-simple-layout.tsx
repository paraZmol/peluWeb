import { Link, usePage } from '@inertiajs/react';
import { motion, useAnimationControls, useReducedMotion } from 'motion/react';
import { useEffect } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const ease = [0.22, 1, 0.36, 1] as const;

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { errors } = usePage().props;
    const reduceMotion = useReducedMotion();
    const shake = useAnimationControls();
    const errorCount = Object.keys(errors ?? {}).length;

    // A short horizontal shake tells "something is wrong" before reading.
    useEffect(() => {
        if (errorCount > 0 && !reduceMotion) {
            void shake.start({
                x: [0, -6, 6, -4, 4, 0],
                transition: { duration: 0.22 },
            });
        }
    }, [errors, errorCount, reduceMotion, shake]);

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <motion.div
                initial={reduceMotion ? false : { opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.35, ease }}
                className="w-full max-w-sm"
            >
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-9 text-primary" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-semibold">{title}</h1>
                            {description && (
                                <p className="text-center text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                    </div>
                    <motion.div animate={shake} className="flex flex-col gap-6">
                        {children}
                    </motion.div>
                </div>
            </motion.div>
        </div>
    );
}
