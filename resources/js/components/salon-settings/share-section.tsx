import { Copy, Download, ExternalLink } from 'lucide-react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { SettingsSection } from './settings-section';
import type { SalonSettings } from './types';

export function ShareSection({ salon }: { salon: SalonSettings }) {
    const [qr, setQr] = useState<string>();

    useEffect(() => {
        void QRCode.toDataURL(salon.publicUrl, { width: 512, margin: 2 }).then(
            setQr,
        );
    }, [salon.publicUrl]);

    const copy = async () => {
        await navigator.clipboard.writeText(salon.publicUrl);
        toast.success('Enlace copiado. Pégalo en tu Instagram o WhatsApp.');
    };

    return (
        <SettingsSection
            id="compartir"
            title="Tu página"
            description="Compártela en tu bio de Instagram, en tus estados de WhatsApp o imprime el QR para el local."
        >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                {qr && (
                    <img
                        src={qr}
                        alt="Código QR de tu página"
                        className="size-40 rounded-lg border bg-white p-1"
                    />
                )}
                <div className="flex min-w-0 flex-col gap-3">
                    <p className="text-sm break-all">{salon.publicUrl}</p>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" onClick={copy}>
                            <Copy /> Copiar enlace
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={salon.publicUrl}
                                target="_blank"
                                rel="noopener"
                            >
                                <ExternalLink /> Ver mi página
                            </a>
                        </Button>
                        {qr && (
                            <Button asChild variant="outline">
                                <a href={qr} download={`qr-${salon.slug}.png`}>
                                    <Download /> Descargar QR
                                </a>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </SettingsSection>
    );
}
