<?php

namespace App\Support;

class Markdown
{
    /**
     * Escape user-provided text before it goes into a Markdown mail, so a
     * salon named "[verifica aquí](https://evil.tld)" stays plain text.
     */
    public static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()<>#+\-.!|~])/', '\\\\$1', $text);
    }
}
