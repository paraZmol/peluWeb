import { releasePush } from '@/lib/push';
import { forgetOfflineCopies } from '@/lib/pwa';
import { Form } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { Label } from '@/components/ui/label';
import { SHEET_ON_PHONE, TOUCH_BUTTON, TOUCH_FOOTER } from '@/lib/sheet';
import { cn } from '@/lib/utils';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Eliminar cuenta"
                description="Elimina tu cuenta de peluWeb"
            />
            {/* Tokens only: the warning reads the same in both modes. */}
            <div className="flex flex-col gap-4 rounded-xl border border-destructive/25 bg-destructive/5 p-4 sm:p-5 dark:border-destructive-foreground/25 dark:bg-destructive/15">
                <div className="flex items-start gap-3">
                    <TriangleAlert
                        className="mt-0.5 size-5 shrink-0 text-destructive-foreground"
                        strokeWidth={1.75}
                        aria-hidden
                    />
                    <div className="flex min-w-0 flex-col gap-2 text-sm leading-relaxed">
                        <p className="text-base leading-snug font-semibold text-destructive-foreground">
                            Advertencia
                        </p>
                        <p className="font-medium">
                            Procede con precaución, esta acción no se puede
                            deshacer.
                        </p>
                        <p className="text-pretty">
                            Se retirarán tus autorizaciones y se borrarán las
                            alergias y fotos que los salones guarden de ti. Los
                            salones donde reservaste conservan tu ficha (nombre,
                            teléfono y citas): para borrarla, pídeselo a cada
                            salón. Tus reseñas siguen publicadas sin tu nombre;
                            si prefieres quitarlas, retíralas antes desde Mis
                            reservas.
                        </p>
                    </div>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                            className={cn(TOUCH_BUTTON, 'sm:self-start')}
                        >
                            Eliminar cuenta
                        </Button>
                    </DialogTrigger>
                    <DialogContent className={SHEET_ON_PHONE}>
                        <DialogHeader className="text-left">
                            <DialogTitle className="pr-8 leading-snug text-balance">
                                ¿Seguro que quieres eliminar tu cuenta?
                            </DialogTitle>
                            <DialogDescription>
                                Tu cuenta se elimina de forma permanente y no se
                                puede recuperar. Ingresa tu contraseña para
                                confirmar.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            // The account is gone: so is the copy of its bookings on this device.
                            onSuccess={() => {
                                forgetOfflineCopies();
                                releasePush();
                            }}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            Contraseña
                                        </Label>

                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder="Contraseña"
                                            autoComplete="current-password"
                                        />

                                        <InputError message={errors.password} />
                                    </div>

                                    <DialogFooter className={TOUCH_FOOTER}>
                                        <DialogClose asChild>
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancelar
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Eliminar cuenta
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
