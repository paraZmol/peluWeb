import { useEffect } from 'react';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { forgetOfflineCopies } from '@/lib/pwa';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    // Whoever reaches a sign-in screen may not be who used this device
    // last (a session that ran out, a shared phone): the copy of their
    // bookings kept for offline use goes, not only on "Cerrar sesión".
    useEffect(forgetOfflineCopies, []);

    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
