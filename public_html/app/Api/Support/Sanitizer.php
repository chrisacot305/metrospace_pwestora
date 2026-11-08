<?php
namespace Api\Support;

final class Sanitizer
{
    /**
     * Cleans user text that gets embedded inside a payment note.
     *
     * Payment notes carry status tags such as "[PENDING]" / "[VERIFIED]" and
     * "|" separators. Without this, a tenant could type "[VERIFIED]" as their
     * reference number and the system would count the payment as verified.
     */
    public static function noteFragment(string $text, int $maxLength = 100): string
    {
        $text = str_replace(['[', ']', '|', "\r", "\n", "\t"], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        return mb_substr(trim($text), 0, $maxLength);
    }
}
