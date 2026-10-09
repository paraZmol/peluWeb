<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Turns the color a salon picks into readable theme tokens for light and
 * dark mode (WCAG contrast). Pure: no framework, fully unit tested.
 *
 * - Button text is white or near-black, whichever contrasts more (≥ 4.5:1
 *   is always reachable with one of the two).
 * - The color itself must stand out from the page background (≥ 3:1, the
 *   WCAG minimum for UI components); too light colors are darkened for
 *   light mode and too dark ones lightened for dark mode.
 * - A dark color with almost no hue (charcoal, graphite) would lighten into a
 *   dull mid gray on a dark page: it becomes a near-white button with dark
 *   text instead, the usual primary of dark interfaces.
 */
final class BrandColor
{
    /** The lightest light background among the templates (Nocturna in light mode). */
    public const string LIGHT_BACKGROUND = '#f3f4f6';

    /** The lightest dark background among the templates (Luminosa in dark mode). */
    public const string DARK_BACKGROUND = '#111318';

    private const string DARK_TEXT = '#1f1b18';

    private const float UI_CONTRAST = 3.0;

    private const float TEXT_CONTRAST = 4.5;

    /** Largest gap between RGB channels (0-255) still read as a neutral. */
    private const int NEUTRAL_CHANNEL_SPREAD = 32;

    /** How far a dark neutral goes toward white in dark mode. */
    private const float NEUTRAL_ON_DARK_MIX = 0.88;

    /**
     * @return array{light: array{primary: string, foreground: string}, dark: array{primary: string, foreground: string}, adjusted: bool}
     */
    public static function tokens(string $hex): array
    {
        $base = self::normalize($hex);

        $light = self::adjustFor($base, self::LIGHT_BACKGROUND, towardWhite: false);
        $dark = self::adjustFor($base, self::DARK_BACKGROUND, towardWhite: true);

        if ($dark !== $base && self::isNeutral($base)) {
            $dark = self::mix($base, '#ffffff', self::NEUTRAL_ON_DARK_MIX);
        }

        return [
            'light' => ['primary' => $light, 'foreground' => self::foregroundFor($light)],
            'dark' => ['primary' => $dark, 'foreground' => self::foregroundFor($dark)],
            'adjusted' => $light !== $base || $dark !== $base,
        ];
    }

    public static function normalize(string $hex): string
    {
        $hex = strtolower(ltrim(trim($hex), '#'));

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-f]{6}$/', $hex)) {
            throw new InvalidArgumentException("Invalid color [{$hex}].");
        }

        return '#'.$hex;
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function isNeutral(string $color): bool
    {
        $channels = self::rgb($color);

        return max($channels) - min($channels) <= self::NEUTRAL_CHANNEL_SPREAD;
    }

    private static function foregroundFor(string $color): string
    {
        return self::contrast($color, '#ffffff') >= self::contrast($color, self::DARK_TEXT)
            ? '#ffffff'
            : self::DARK_TEXT;
    }

    /**
     * Mix the color toward black (light mode) or white (dark mode) in small
     * steps until it both stands out from the background and keeps its
     * button text readable. Both contrasts only grow in that direction, so
     * the loop always ends with the smallest change that works.
     */
    private static function adjustFor(string $color, string $background, bool $towardWhite): string
    {
        $target = $towardWhite ? '#ffffff' : '#000000';

        for ($step = 0; $step <= 20; $step++) {
            $candidate = self::mix($color, $target, $step / 20);

            if (self::contrast($candidate, $background) >= self::UI_CONTRAST
                && self::contrast($candidate, self::foregroundFor($candidate)) >= self::TEXT_CONTRAST) {
                return $candidate;
            }
        }

        return $target;
    }

    private static function mix(string $from, string $to, float $amount): string
    {
        [$r1, $g1, $b1] = self::rgb($from);
        [$r2, $g2, $b2] = self::rgb($to);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r1 + ($r2 - $r1) * $amount),
            (int) round($g1 + ($g2 - $g1) * $amount),
            (int) round($b1 + ($b2 - $b1) * $amount),
        );
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * @return array{int, int, int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex), '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
