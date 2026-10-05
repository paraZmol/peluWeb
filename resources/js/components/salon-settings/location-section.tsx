import { Form } from '@inertiajs/react';
import { Crosshair } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { parseCoordinates, SalonMap } from '@/components/map/salon-map';
import type { Coordinates } from '@/components/map/salon-map';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/salon/location';
import { SettingsSection } from './settings-section';
import type { SalonSettings } from './types';

export function LocationSection({ salon }: { salon: SalonSettings }) {
    const [position, setPosition] = useState<Coordinates | null>(
        salon.latitude !== null && salon.longitude !== null
            ? { latitude: salon.latitude, longitude: salon.longitude }
            : null,
    );
    const [hint, setHint] = useState<string>();

    const useMyLocation = () => {
        if (!navigator.geolocation) {
            setHint(
                'Tu navegador no permite obtener la ubicación. Toca el mapa para marcarla.',
            );

            return;
        }

        navigator.geolocation.getCurrentPosition(
            ({ coords }) =>
                setPosition({
                    latitude: coords.latitude,
                    longitude: coords.longitude,
                }),
            () =>
                setHint(
                    'No pudimos obtener tu ubicación. Toca el mapa o pega un enlace de Google Maps.',
                ),
            { enableHighAccuracy: true, timeout: 10000 },
        );
    };

    const fromLink = (text: string) => {
        const parsed = parseCoordinates(text);
        setHint(
            parsed
                ? undefined
                : 'No encontramos coordenadas en ese enlace. Prueba con "Compartir" en Google Maps.',
        );

        if (parsed) {
            setPosition(parsed);
        }
    };

    return (
        <SettingsSection
            id="ubicacion"
            title="Ubicación"
            description="Para que te encuentren en el mapa y lleguen con un toque."
        >
            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2 sm:col-span-3">
                                <Label htmlFor="address">Dirección</Label>
                                <Input
                                    id="address"
                                    name="address"
                                    defaultValue={salon.address ?? ''}
                                    placeholder="Av. Larco 345, tienda 2"
                                />
                                <InputError message={errors.address} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="district">Distrito</Label>
                                <Input
                                    id="district"
                                    name="district"
                                    defaultValue={salon.district ?? ''}
                                    placeholder="Miraflores"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="city">Ciudad</Label>
                                <Input
                                    id="city"
                                    name="city"
                                    defaultValue={salon.city ?? ''}
                                    placeholder="Lima"
                                />
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={useMyLocation}
                                >
                                    <Crosshair /> Estoy en el salón: usar mi
                                    ubicación
                                </Button>
                            </div>
                            <Input
                                aria-label="Pegar enlace de Google Maps"
                                placeholder="O pega el enlace de Google Maps"
                                onChange={(event) =>
                                    event.target.value &&
                                    fromLink(event.target.value)
                                }
                            />
                            <p className="text-sm text-muted-foreground">
                                También puedes tocar el mapa o arrastrar el pin.
                            </p>
                            <InputError message={hint ?? errors.latitude} />
                        </div>

                        <SalonMap
                            position={position}
                            onChange={setPosition}
                            className="h-72 w-full rounded-xl border"
                        />

                        <input
                            type="hidden"
                            name="latitude"
                            value={position?.latitude ?? ''}
                        />
                        <input
                            type="hidden"
                            name="longitude"
                            value={position?.longitude ?? ''}
                        />

                        <Button
                            type="submit"
                            className="rounded-full sm:self-end"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Guardar ubicación
                        </Button>
                    </>
                )}
            </Form>
        </SettingsSection>
    );
}
