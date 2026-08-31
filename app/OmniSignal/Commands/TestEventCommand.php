<?php

namespace App\OmniSignal\Commands;

use App\OmniSignal\ConversionManager;
use App\OmniSignal\DTO\ConversionPayload;
use Illuminate\Console\Command;

class TestEventCommand extends Command
{
    protected $signature = 'omnisignal:test-event 
                            {--channel= : Target ad channel (google, meta, tiktok, linkedin, microsoft, all)}
                            {--event=Purchase : Event name}
                            {--value=120.00 : Conversion value}
                            {--currency=USD : Currency code}
                            {--order-id= : Custom order/transaction ID}';

    protected $description = 'Dispatches a live or test conversion event to configured ad channels to verify CAPI delivery';

    public function handle(ConversionManager $manager): int
    {
        $channel = $this->option('channel') ?: 'all';
        $eventName = (string) $this->option('event');
        $value = (float) $this->option('value');
        $currency = (string) $this->option('currency');
        $orderId = $this->option('order-id') ?: 'TEST_'.time();

        $this->info("🕉️ OmniSignal Test Event Dispatcher");
        $this->line("────────────────────────────────────────");
        $this->line("Event:    <fg=cyan>{$eventName}</>");
        $this->line("Value:    <fg=green>{$currency} {$value}</>");
        $this->line("Order ID: <fg=yellow>{$orderId}</>");
        $this->line("Target:   <fg=magenta>{$channel}</>");
        $this->line("────────────────────────────────────────");

        $payload = new ConversionPayload(
            eventName: $eventName,
            value: $value,
            currency: $currency,
            orderId: $orderId,
            userData: [
                'email' => 'test@omnisignal.dev',
                'phone' => '+15551234567',
                'first_name' => 'OmniSignal',
                'last_name' => 'Tester',
            ],
            fbclid: 'test_fbclid_cli_verifier',
            ttclid: 'test_ttclid_cli_verifier'
        );

        $channels = $channel === 'all'
            ? config('omnisignal.enabled_channels', ['google', 'meta', 'tiktok', 'linkedin', 'microsoft'])
            : [$channel];

        foreach ($channels as $ch) {
            try {
                $driver = $manager->driver($ch);
                if (! $driver->isConfigured()) {
                    $this->warn("⚠️  [{$ch}] Skipping: credentials not configured in .env");
                    continue;
                }

                $this->output->write("⏳ Uploading to [{$ch}]... ");
                $res = $driver->upload([$payload]);

                if ($res['success'] ?? false) {
                    $this->line("<fg=green>SUCCESS (Sent {$res['count']} event)</>");
                } else {
                    $errors = implode(', ', $res['errors'] ?? ['Unknown error']);
                    $this->line("<fg=red>FAILED: {$errors}</>");
                }
            } catch (\Throwable $e) {
                $this->error("❌ [{$ch}] Exception: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("✨ Test event cycle complete. Check your ad platform event manager or /dashboard!");

        return Command::SUCCESS;
    }
}
