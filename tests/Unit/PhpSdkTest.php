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

    public function test_client_records_and_fans_out(): void
    {
        $client = OmniSignalClient::create([
            'google' => ['customer_id' => '123-456-7890'],
            'microsoft' => ['customer_id' => '987654321', 'developer_token' => 'DEV_TOKEN'],
            'linkedin' => ['access_token' => 'LI_TOKEN'],
        ]);

        $results = $client->record(
            eventName: 'Lead',
            value: 50.0,
            currency: 'USD',
            orderId: 'LEAD-99',
            user: ['email' => 'client@agency.com'],
            clickIds: ['gclid' => 'gclid_test', 'msclkid' => 'ms_test']
        );

        $this->assertArrayHasKey('google', $results);
        $this->assertTrue($results['google']['success']);
        $this->assertArrayHasKey('microsoft', $results);
        $this->assertTrue($results['microsoft']['success']);
        $this->assertArrayHasKey('linkedin', $results);
        $this->assertTrue($results['linkedin']['success']);
    }
}
