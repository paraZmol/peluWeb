import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import TextLink from '@/components/text-link';
import { home, login, register } from '@/routes';
import { accept } from '@/routes/invitations';

type Props = {
    token: string;
    invitation: {
        salonName: string;
        role: string;
        email: string;
    } | null;
};

export default function ShowInvitation({ token, invitation }: Props) {
    const { auth } = usePage().props;

    if (!invitation) {
        return (
            <>
                <Head title="Invitación no válida" />
                <p className="text-center text-sm text-muted-foreground">
                    El enlace venció, ya se usó o fue cancelado. Pide a quien te
                    invitó que te envíe uno nuevo.
                </p>
                <TextLink href={home()} className="text-center text-sm">
                    Ir al inicio
                </TextLink>
            </>
        );
    }

    const signedInWithOtherEmail =
        auth.user && auth.user.email.toLowerCase() !== invitation.email;

    return (
        <>
            <Head title={`Únete a ${invitation.salonName}`} />

            <p className="text-center">
                <span className="font-semibold">{invitation.salonName}</span> te
                invitó a su equipo como{' '}
                <span className="font-semibold">{invitation.role}</span>.
            </p>

            {!auth.user && (
                <div className="flex flex-col gap-3">
                    <p className="text-center text-sm text-muted-foreground">
                        Usa el correo {invitation.email} para continuar.
                    </p>
                    <Button asChild className="w-full rounded-full">
                        <Link
                            href={register({
                                query: { email: invitation.email },
                            })}
                        >
                            Crear cuenta
                        </Link>
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        className="w-full rounded-full"
                    >
                        <Link href={login()}>Ya tengo cuenta</Link>
                    </Button>
                </div>
            )}

            {signedInWithOtherEmail && (
                <p className="text-center text-sm text-muted-foreground">
                    Iniciaste sesión como {auth.user?.email}, pero la invitación
                    es para {invitation.email}. Cierra sesión y entra con ese
                    correo.
                </p>
            )}

            {auth.user && !signedInWithOtherEmail && (
                <Form {...accept.form(token)}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            className="w-full rounded-full"
                            disabled={processing}
                            data-test="accept-invitation"
                        >
                            {processing && <Spinner />}
                            Unirme al equipo
                        </Button>
                    )}
                </Form>
            )}
        </>
    );
}

ShowInvitation.layout = {
    title: 'Invitación al equipo',
    description: '',
};
