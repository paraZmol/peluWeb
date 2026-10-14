<?php

namespace App\Domain\Promotions;

use App\Enums\PromotionMechanic;
use App\Support\Money;

/**
 * The words of a promotion, written by the system from its terms: the
 * title the client reads and the fine print under it. The owner never
 * types the fine print, and a title of their own is shown with this one,
 * so what is promised is always what the engine does
 * (docs/promociones.md, "Letra chica"). Pure: names and numbers in, text out.
 */
final class PromotionCopy
{
    private const array WEEKDAYS = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    private const array MONTHS = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** With more services than this, the title says "y más" after the first two. */
    private const int NAMES_IN_TITLE = 2;

    /**
     * "20 % menos en Corte", "S/ 10 menos en Manicure", "Rasurado gratis con tu Corte".
     *
     * @param  list<string>  $serviceNames  the included services, in catalogue order
     */
    public static function title(PromotionTerms $terms, array $serviceNames, ?string $giftName = null, ?ComboPrice $combo = null): string
    {
        $services = self::names($serviceNames);
        $title = match (true) {
            // Without its price, the name it is stored under: prices change, a name must not lie.
            $terms->mechanic === PromotionMechanic::Combo && $combo === null => 'Combo '.implode(' + ', $serviceNames),
            $terms->mechanic === PromotionMechanic::Combo => implode(' + ', $serviceNames)
                .($combo->isFrom ? ' desde S/ ' : ' por S/ ').self::soles($combo->priceCents()),
            $terms->mechanic === PromotionMechanic::Gift => "{$giftName} gratis con tu {$services}",
            $terms->percent !== null => "{$terms->percent} % menos en {$services}",
            default => 'S/ '.self::soles((int) $terms->amountCents)." menos en {$services}",
        };

        return $terms->newClientsOnly ? "{$title} en tu primera visita" : $title;
    }

    /**
     * What it is valid for, said in full and the same way every time:
     * whose it is, how long it runs, how many it takes and every limit the
     * engine applies, so nothing it does comes as a surprise.
     *
     * @param  list<string>  $serviceNames
     */
    public static function finePrint(PromotionTerms $terms, array $serviceNames, PromotionFacts $facts): string
    {
        $parts = [
            "Promoción de {$facts->salonName}.",
            'Válido para citas '.self::period($terms->startsOn, $terms->endsOn).'.',
            'Aplica a: '.implode(', ', $serviceNames).'.',
        ];

        if (($when = self::startingWhen($terms)) !== null) {
            $parts[] = "Solo {$when}.";
        }

        $parts[] = $facts->quota === null
            ? 'Sin límite de cupos: vale para todas las citas que se reserven dentro de estas fechas, mientras haya horarios libres.'
            : "Cupos: {$facts->quota} ".($facts->quota === 1 ? 'reserva' : 'reservas').'.';

        if ($terms->newClientsOnly) {
            $parts[] = "Solo para tu primera reserva en {$facts->salonName}: no vale si ya tuviste una cita allí, aunque no hayas asistido, ni si tienes otra reservada. Las citas canceladas no cuentan. Lo comprobamos con tu cuenta y tu teléfono.";
        } elseif ($facts->oncePerClient) {
            $parts[] = 'Una por cliente.';
        }

        if ($terms->mechanic === PromotionMechanic::Combo) {
            $parts[] = 'El precio del combo es por '.self::list($serviceNames).' juntos, en la misma cita y atendidos por una sola persona del equipo. '
                .'Si eliges una opción o un profesional con un precio más alto, pagas esa diferencia y sigues ahorrando S/ '.self::soles((int) $terms->amountCents).'. '
                .'Si quitas uno de los servicios, el combo no aplica y los demás se cobran a su precio normal.';
        } elseif ($terms->amountCents !== null && count($serviceNames) > 1) {
            $parts[] = 'El descuento de S/ '.self::soles($terms->amountCents).' se aplica una sola vez por reserva.';
        }

        if ($terms->mechanic === PromotionMechanic::Gift) {
            $parts[] = self::giftTerms($facts);
        }

        $parts[] = 'Si cambias tu cita, la promoción se mantiene solo si la nueva fecha y hora cumplen estas condiciones.';

        if ($facts->hasDeposit) {
            $parts[] = 'El precio con promoción se reserva hasta el plazo para enviar la seña.';
        }

        $parts[] = 'Con reserva previa. No se suma a otras promociones: si tu cita cumple más de una, se aplica la de mayor ahorro. Precios finales en soles, con impuestos incluidos.';

        return implode(' ', $parts);
    }

    /**
     * "del 5 al 18 de octubre de 2026"; with the month, and the year, of
     * each end when they differ.
     */
    public static function period(string $startsOn, string $endsOn): string
    {
        [$fromYear, $fromMonth, $fromDay] = array_map(intval(...), explode('-', $startsOn));
        [$toYear, $toMonth, $toDay] = array_map(intval(...), explode('-', $endsOn));
        $to = "{$toDay} de ".self::MONTHS[$toMonth]." de {$toYear}";

        return match (true) {
            $fromYear !== $toYear => "del {$fromDay} de ".self::MONTHS[$fromMonth]." de {$fromYear} al {$to}",
            $fromMonth !== $toMonth => "del {$fromDay} de ".self::MONTHS[$fromMonth]." al {$to}",
            default => "del {$fromDay} al {$to}",
        };
    }

    /**
     * What the gift is worth and what taking it means for the visit.
     */
    private static function giftTerms(PromotionFacts $facts): string
    {
        $worth = $facts->giftPriceCents === null ? '' : ', precio normal S/ '.self::soles($facts->giftPriceCents);
        $longer = $facts->giftMinutes === null ? '' : " y alarga la cita {$facts->giftMinutes} min";

        return "El regalo ({$facts->giftName}{$worth}) se hace en la misma cita{$longer}. No se cambia por dinero ni por otro servicio.";
    }

    /**
     * The days and hours as the engine reads them: by when the visit starts.
     */
    private static function startingWhen(PromotionTerms $terms): ?string
    {
        $days = $terms->weekdays === null ? null
            : self::list(array_map(fn (int $day): string => self::WEEKDAYS[$day], $terms->weekdays));
        $hours = $terms->fromMinute === null || $terms->toMinute === null ? null
            : 'en citas que empiecen entre las '.self::clock($terms->fromMinute).' y las '.self::clock($terms->toMinute);

        return match (true) {
            $days !== null && $hours !== null => "{$days}, {$hours}",
            default => $days ?? $hours,
        };
    }

    /**
     * "lunes, martes y miércoles, de 10:00 a 16:00"; null when it names neither.
     */
    public static function when(PromotionTerms $terms): ?string
    {
        $days = $terms->weekdays === null ? null
            : self::list(array_map(fn (int $day): string => self::WEEKDAYS[$day], $terms->weekdays));
        $hours = $terms->fromMinute === null || $terms->toMinute === null ? null
            : 'de '.self::clock($terms->fromMinute).' a '.self::clock($terms->toMinute);

        return match (true) {
            $days !== null && $hours !== null => "{$days}, {$hours}",
            default => $days ?? $hours,
        };
    }

    /**
     * @param  list<string>  $names
     */
    private static function names(array $names): string
    {
        return count($names) > self::NAMES_IN_TITLE
            ? implode(', ', array_slice($names, 0, self::NAMES_IN_TITLE)).' y más'
            : self::list($names);
    }

    /**
     * "A", "A y B", "A, B y C".
     *
     * @param  list<string>  $items
     */
    private static function list(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items)." y {$last}";
    }

    /** 600 → "10:00". */
    private static function clock(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60) % 24, $minute % 60);
    }

    /** 1000 → "10", 1050 → "10.50". */
    private static function soles(int $cents): string
    {
        return $cents % 100 === 0 ? (string) intdiv($cents, 100) : Money::toAmount($cents);
    }
}
