<?php

namespace App\Domain\Legal;

use InvalidArgumentException;

/**
 * A versioned legal text as kept in resources/legal: a header of
 * `key: value` lines between `---` and the Markdown body, whose gaps are
 * written `{{ key }}` and filled from configuration.
 */
final class LegalSource
{
    private const string PLACEHOLDER = '/\{\{\s*([a-z_]+)\s*\}\}/';

    /**
     * @param  array<string, string>  $meta
     */
    private function __construct(
        public readonly array $meta,
        public readonly string $body,
    ) {}

    public static function parse(string $source): self
    {
        $source = str_replace("\r\n", "\n", $source);

        if (preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $source, $parts) !== 1) {
            throw new InvalidArgumentException('A legal text starts with its --- header.');
        }

        $meta = [];

        foreach (explode("\n", $parts[1]) as $line) {
            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');

            if (trim($key) !== '') {
                $meta[trim($key)] = trim($value);
            }
        }

        return new self($meta, trim($parts[2])."\n");
    }

    public function meta(string $key): string
    {
        return $this->meta[$key] ?? throw new InvalidArgumentException("The legal text has no `{$key}` in its header.");
    }

    /**
     * The body with every gap filled. A gap without a value reads
     * «por completar: …» while that is acceptable (outside production);
     * otherwise a dash, so no draft wording reaches the public.
     *
     * @param  array<string, string>  $values
     * @param  array<string, string>  $labels  what each gap stands for, in Spanish
     * @return array{text: string, missing: list<string>}
     */
    public function fill(array $values, array $labels, bool $showMissing): array
    {
        $missing = [];

        $text = (string) preg_replace_callback(self::PLACEHOLDER, function (array $match) use ($values, $labels, $showMissing, &$missing): string {
            $value = trim($values[$match[1]] ?? '');

            if ($value !== '') {
                return $value;
            }

            $missing[] = $match[1];

            return $showMissing ? '«por completar: '.($labels[$match[1]] ?? $match[1]).'»' : '—';
        }, $this->body);

        return ['text' => $text, 'missing' => array_values(array_unique($missing))];
    }
}
