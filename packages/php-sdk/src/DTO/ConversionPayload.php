<?php

namespace OmniSignal\DTO;

class ConversionPayload
{
    public function __construct(
        public string $eventName,
        public float $value = 0.0,
        public string $currency = 'USD',
        public ?string $orderId = null,
        public int $timestamp = 0,
        public array $userData = [],
        public ?string $gclid = null,
        public ?string $gbraid = null,
        public ?string $wbraid = null,
        public ?string $fbclid = null,
        public ?string $ttclid = null,
        public ?string $msclkid = null,
        public ?string $liFatId = null,
        public ?string $visitorId = null,
        public array $customData = [],
        public string $actionSource = 'website'
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = time();
        }
    }
}
