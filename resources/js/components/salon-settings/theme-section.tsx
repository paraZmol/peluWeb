import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { SALON_THEME_ORDER } from '@/components/salon/salon-themes';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/salon/theme';
import type { Option } from '@/types';
import { SettingsSection } from './settings-section';
import { ThemeOption } from './theme-option';
import { ThemePreviewDialog } from './theme-preview-dialog';
import { miniSalonFrom } from './theme-samples';
import type { SalonSettings, ThemeChoice } from './types';

type Props = {
    salon: SalonSettings;
    themes: ThemeChoice[];
    businessTypes: Option[];
};

/** "Diseño de tu página": the template of the public page, booking and Mis reservas. */
export function ThemeSection({ salon, themes, businessTypes }: Props) {
    const [selected, setSelected] = useState(salon.theme);
    const [previewing, setPreviewing] = useState<ThemeChoice | null>(null);
    const miniSalon = useMemo(
        () => miniSalonFrom(salon, businessTypes),
        [salon, businessTypes],
    );
    const ordered = [...themes].sort(
        (a, b) =>
            SALON_THEME_ORDER.indexOf(a.value) -
            SALON_THEME_ORDER.indexOf(b.value),
    );

    return (
        <SettingsSection
            id="diseno"
            title="Diseño de tu página"
            description="Así verán tu página quienes quieran reservar contigo. Si aún no tienes fotos, la vista previa usa fotos de muestra."
        >
            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div
                            role="radiogroup"
                            aria-labelledby="diseno-title"
                            className="grid grid-cols-2 gap-3 md:grid-cols-4"
                        >
                            {ordered.map((choice) => (
                                <ThemeOption
                                    key={choice.value}
                                    choice={choice}
                                    salon={miniSalon}
                                    brand={salon.brand}
                                    isSelected={selected === choice.value}
                                    isCurrent={salon.theme === choice.value}
                                    onSelect={() => setSelected(choice.value)}
                                    onPreview={() => setPreviewing(choice)}
                                />
                            ))}
                        </div>
                        <InputError message={errors.theme} />

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <p
                                role="status"
                                className="flex items-start gap-2 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-sm empty:hidden"
                            >
                                {selected !== salon.theme && (
                                    <>
                                        <span
                                            aria-hidden
                                            className="mt-1.5 size-2 shrink-0 rounded-full bg-warning"
                                        />
                                        <span>
                                            <strong className="font-semibold">
                                                Cambios sin guardar.
                                            </strong>{' '}
                                            Tus clientes siguen viendo el diseño
                                            anterior.
                                        </span>
                                    </>
                                )}
                            </p>
                            <Button
                                type="submit"
                                className="rounded-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Guardar diseño
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <ThemePreviewDialog
                choice={previewing}
                brand={salon.brand}
                isSelected={previewing?.value === selected}
                onUse={(choice) => {
                    setSelected(choice.value);
                    setPreviewing(null);
                }}
                onClose={() => setPreviewing(null)}
            />
        </SettingsSection>
    );
}
