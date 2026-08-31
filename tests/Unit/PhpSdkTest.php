<?php

namespace Tests\Unit;

use OmniSignal\DTO\ConversionPayload;
use OmniSignal\OmniSignalClient;
use PHPUnit\Framework\TestCase;

class PhpSdkTest extends TestCase
{
    public function test_conversion_payload_initializes_with_defaults(): void
    {
        $payload = new ConversionPayload(
            eventName: 'Purchase',
            value: 99.95,
            currency: 'USD',
            orderId: 'ORD-1234',
            userData: ['email' => 'buyer@test.com'],
            fbclid: 'fb_click_test'
        );

        $this->assertEquals('Purchase', $payload->eventName);
        $this->assertEquals(99.95, $payload->value);
        $this->assertEquals('USD', $payload->currency);
        $this->assertEquals('ORD-1234', $payload->orderId);
        $this->assertEquals('fb_click_test', $payload->fbclid);
        $this->assertGreaterThan(0, $payload->timestamp);
    }

    public function test_a_half_configured_channel_is_not_registered(): void
    {
        // These drivers used to accept one credential and then report success
        // for every conversion without making a request. A channel that cannot
        // actually deliver must not be registered at all.
        $client = OmniSignalClient::create([
            'google' => ['customer_id' => '123-456-7890'],
            'microsoft' => ['customer_id' => '987654321', 'developer_token' => 'DEV_TOKEN'],
            'linkedin' => ['access_token' => 'LI_TOKEN'],
        ]);

        $this->assertSame([], $client->activeChannels());

        $results = $client->record(
            eventName: 'Lead',
            value: 50.0,
            currency: 'USD',
            orderId: 'LEAD-99',
            user: ['email' => 'client@agency.com'],
            clickIds: ['gclid' => 'gclid_test', 'msclkid' => 'ms_test'],
        );

        $this->assertSame([], $results);
    }

    public function test_fully_configured_channels_are_registered(): void
    {
        $client = OmniSignalClient::create([
            'google' => [
                'developer_token' => 'DEV', 'client_id' => 'cid', 'client_secret' => 'sec',
                'refresh_token' => 'ref', 'customer_id' => '123-456-7890', 'conversion_action' => '555',
            ],
            'microsoft' => [
                'developer_token' => 'DEV', 'customer_id' => '987654321',
                'account_id' => '111', 'access_token' => 'MS_TOKEN',
            ],
            'linkedin' => ['access_token' => 'LI_TOKEN', 'conversion_rule_id' => '42'],
        ]);

        $this->assertSame(['google', 'linkedin', 'microsoft'], $client->activeChannels());
    }
}
