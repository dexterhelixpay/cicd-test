<?php

namespace App\Messages;

use Illuminate\Notifications\Messages\SimpleMessage;
use Illuminate\Support\Traits\Conditionable;

class SmsMessage extends SimpleMessage
{
    use Conditionable;

    /**
     * Format the given line of text.
     *
     * @param  \Illuminate\Contracts\Support\Htmlable|string|array  $line
     * @return \Illuminate\Contracts\Support\Htmlable|string
     */
    protected function formatLine($line)
    {
        if (is_array($line)) {
            return implode(' ', array_map(function ($line) {
               return trim($line ?? '', " \t\0\x0B");
            }, $line));
        }

        return trim($line ?? '', " \t\0\x0B");
    }

    /**
     * Returns a string representation of the message.
     *
     * @return string
     */
    public function __toString()
    {
        $message = collect($this->introLines)->join("\n");

        if ($this->salutation) {
            $message .= "\n\n\n{$this->salutation}";
        }

        return $message;
    }
}
