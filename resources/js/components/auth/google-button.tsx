import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { redirect } from '@/routes/google';

/**
 * Plain link on purpose: the OAuth flow leaves the SPA for google.com.
 * Hidden until GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are configured.
 */
export function GoogleButton() {
    const { features } = usePage().props;

    if (!features.googleLogin) {
        return null;
    }

    return (
        <>
            <Button asChild variant="outline" className="w-full rounded-full">
                <a href={redirect().url} data-test="google-login">
                    <GoogleIcon />
                    Continuar con Google
                </a>
            </Button>
            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                <span className="h-px flex-1 bg-border" />o con tu correo
                <span className="h-px flex-1 bg-border" />
            </div>
        </>
    );
}

function GoogleIcon() {
    return (
        <svg viewBox="0 0 24 24" className="size-4" aria-hidden="true">
            <path
                fill="#4285F4"
                d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.2-2.1 3.5-5.1 3.5-8.8Z"
            />
            <path
                fill="#34A853"
                d="M12 24c3.2 0 6-1.1 7.9-2.9l-3.9-3c-1.1.7-2.4 1.2-4 1.2-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1A12 12 0 0 0 12 24Z"
            />
            <path
                fill="#FBBC05"
                d="M5.4 14.4a7.2 7.2 0 0 1 0-4.7V6.6h-4a12 12 0 0 0 0 10.9l4-3.1Z"
            />
            <path
                fill="#EA4335"
                d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.4 6.6l4 3.1C6.3 6.9 8.9 4.8 12 4.8Z"
            />
        </svg>
    );
}
