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
                .'Solo lo verán quien dirige el salón y quien me atiende. '
                .'Se guarda en peluWeb, la plataforma de reservas que usa el salón. No se usa para publicidad ni se entrega a nadie más. '
                .'Se conserva mientras no retire esta autorización. '
                .'Puedo ver lo anotado y retirar esta autorización cuando quiera desde mi cuenta de peluWeb, y pedir en el salón que lo corrijan o que la retiren por mí; al retirarla, se borra.',
            self::Photos => "Autorizo a {$salonName} a tomar y guardar fotos del antes y el después de mi servicio, solo para su registro interno y para mis próximas citas. "
                .'Es voluntario: si no autorizo, me atienden igual. '
                .'Solo las verán quien dirige el salón y quien me atiende. No se publicarán ni se entregarán a nadie más. '
                .'Se guardan en peluWeb, la plataforma de reservas que usa el salón. '
                .'Puedo verlas y retirar esta autorización cuando quiera desde mi cuenta de peluWeb o pidiéndolo en el salón; al retirarla, se borran todas.',
        };
    }

    /**
     * The box the person ticks in their account, word for word: their
     * declaration of age is part of what they agreed to.
     */
    public function acceptLabel(string $salonName): string
    {
        return match ($this) {
            self::Care => "Declaro que soy mayor de 18 años. Leí el texto de arriba y autorizo a {$salonName} a guardar estos datos de salud.",
            self::Photos => "Declaro que soy mayor de 18 años. Leí el texto de arriba y autorizo a {$salonName} a tomar y guardar estas fotos.",
        };
    }

    /**
     * Everything shown to a person who authorizes from their account, as
     * it is hashed into the proof.
     */
    public function accountText(string $salonName): string
    {
        return $this->text($salonName)."\n\n".$this->acceptLabel($salonName);
    }

    /** The current wording the person signs; bump it when the text or the box changes. */
    public function textVersion(): string
    {
        return match ($this) {
            self::Care => '0.5',
            self::Photos => '0.3',
        };
    }
}
