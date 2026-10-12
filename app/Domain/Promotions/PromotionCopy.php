<?php

namespace App\Domain\Promotions;

use App\Enums\PromotionMechanic;
use App\Support\Money;

/**
 * The words of a promotion, written by the system from its terms: the
 * title the client reads and the fine print under it. The owner never
 * types either, so what is promised is always what the engine does
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
    public static function title(PromotionTerms $terms, array $serviceNames, ?string $giftName = null): string
    {
        $services = self::names($serviceNames);

        return match (true) {
            $terms->mechanic === PromotionMechanic::Gift => "{$giftName} gratis con tu {$services}",
            $terms->percent !== null => "{$terms->percent} % menos en {$services}",
            default => 'S/ '.self::soles((int) $terms->amountCents)." menos en {$services}",
        };
    }

    /**
     * What it is valid for, said in full and the same way every time.
     *
     * @param  list<string>  $serviceNames
     */
    public static function finePrint(PromotionTerms $terms, array $serviceNames, ?int $quota, bool $oncePerClient): string
    {
        $parts = [
            'Válido para citas del '.self::day($terms->startsOn).' al '.self::day($terms->endsOn).'.',
            'Aplica a: '.implode(', ', $serviceNames).'.',
        ];

        if (($when = self::when($terms)) !== null) {
            $parts[] = "Solo {$when}.";
        }

        $parts[] = $quota === null ? 'Sin límite de cupos, sujeto a horarios disponibles.' : "Cupos: {$quota}.";

        if ($oncePerClient) {
            $parts[] = 'Una por cliente.';
        }

        if ($terms->mechanic === PromotionMechanic::Gift) {
            $parts[] = 'El servicio de regalo se realiza en la misma cita y no es canjeable por dinero ni por otro servicio.';
        }

        $parts[] = 'Con reserva previa. No acumulable con otras promociones. Precios en soles con IGV incluido.';

        return implode(' ', $parts);
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

    /** "5 de octubre". */
    private static function day(string $date): string
    {
        [, $month, $day] = array_map(intval(...), explode('-', $date));

        return "{$day} de ".self::MONTHS[$month];
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
