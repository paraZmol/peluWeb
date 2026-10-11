<?php

namespace App\Enums;

/**
 * What a client authorized a salon to keep about them.
 */
enum ConsentKind: string
{
    /** Allergies and reactions: health data. */
    case Care = 'care';

    /** Before/after pictures for the salon's own record, never published. */
    case Photos = 'photos';

    /**
     * What the person agrees to, word for word (drafts in
     * docs/legal/consentimientos.md, rows 17 and 20).
     */
    public function text(string $salonName): string
    {
        return match ($this) {
            self::Care => "Autorizo a {$salonName} a guardar mis alergias, sensibilidades y reacciones a productos (datos de salud) para atenderme con seguridad. "
                .'Es voluntario: si no autorizo, me atienden igual y no se anota. '
                .'Lo verán solo quien dirige el salón y quien me atiende. '
                .'Se guarda en peluWeb, la plataforma de reservas que usa el salón. No se usa para publicidad ni se entrega a nadie más. '
                .'Puedo ver lo anotado y retirar esta autorización cuando quiera, desde mi cuenta de peluWeb o pidiéndolo en el salón; al retirarla, se borra.',
            self::Photos => "Autorizo a {$salonName} a tomar y guardar fotos del antes y el después de mi servicio, solo para su registro interno y para mis próximas citas. "
                .'Solo las verán quien dirige el salón y quien me atiende. No se publicarán. '
                .'Puedo verlas y borrarlas cuando quiera desde mi cuenta de peluWeb o pidiéndolo en el salón.',
        };
    }

    /** The current wording the person signs; bump it when the text changes. */
    public function textVersion(): string
    {
        return match ($this) {
            self::Care => '0.4',
            self::Photos => '0.2',
        };
    }
}
