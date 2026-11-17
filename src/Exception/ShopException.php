<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * A business rule was broken ("not enough stock", "payment declined"...).
 * The message is a translation key, so the controller can show it as a flash in any language.
 */
final class ShopException extends \RuntimeException
{
    /** @param array<string, string|int> $parameters values injected in the translated message */
    public function __construct(string $translationKey, private readonly array $parameters = [])
    {
        parent::__construct($translationKey);
    }

    /** @return array<string, string|int> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /** Ready to be passed to addFlash(): Twig translates it in the visitor's language. */
    public function toTranslatable(): TranslatableMessage
    {
        return new TranslatableMessage($this->getMessage(), $this->parameters);
    }
}
