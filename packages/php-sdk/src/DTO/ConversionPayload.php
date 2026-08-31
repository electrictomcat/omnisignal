<?php

namespace OmniSignal\DTO;

/**
 * One conversion, in the shape every channel driver reads.
 *
 * @property array<string, mixed> $userData First-party identifiers. Commonly
 *                                          email, phone (or phone_number), client_ip, client_user_agent.
 */
class ConversionPayload
{
    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customData
     */
    public function __construct(
        public string $eventName,
        // Nullable: a conversion may legitimately carry no monetary value, and
        // defaulting to 0.0 sent a real zero-value conversion to every channel.
        public ?float $value = null,
        public ?string $currency = 'USD',
        public ?string $orderId = null,
        public int $timestamp = 0,
        public array $userData = [],
        public ?string $gclid = null,
        public ?string $gbraid = null,
        public ?string $wbraid = null,
        public ?string $fbclid = null,
        public ?string $fbc = null,
        public ?string $fbp = null,
        public ?string $ttclid = null,
        public ?string $msclkid = null,
        public ?string $liFatId = null,
        public ?string $visitorId = null,
        public array $customData = [],
        public string $actionSource = 'website',
        public ?string $eventSourceUrl = null,
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = time();
        }

        if ($this->currency !== null) {
            $this->currency = strtoupper(trim($this->currency));
        }
    }

    /**
     * Any click identifier this conversion carries.
     */
    public function primaryClickId(): ?string
    {
        return $this->gclid
            ?? $this->gbraid
            ?? $this->wbraid
            ?? $this->fbclid
            ?? $this->msclkid
            ?? $this->ttclid
            ?? $this->liFatId;
    }
}
