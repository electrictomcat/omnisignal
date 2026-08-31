<?php

namespace App\Listeners;

use ElectricTomCat\GoogleAdsConversions\Events\ConversionUploadFailed;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Surfaces conversion upload failures instead of letting them sit in a log.
 *
 * The engine reports rejections properly now, but a failure nobody sees is
 * indistinguishable from one that never happened — which is the failure mode
 * this whole pipeline exists to avoid. Failures are counted per hour and, once
 * they cross a threshold, escalated once rather than per event, so a broken
 * credential produces one alert instead of ten thousand.
 */
class ReportConversionFailure
{
    /** Failures in an hour before an alert is raised. */
    protected const ALERT_THRESHOLD = 10;

    public function handle(ConversionUploadFailed $event): void
    {
        Log::error('[OmniSignal] Conversion upload failed', [
            'click_id' => $event->clickId,
            'reason' => $event->errorMessage,
            'event' => $event->conversion['event'] ?? null,
            'order_id' => $event->conversion['order_id'] ?? null,
        ]);

        $bucket = 'conversion_failures:'.now()->format('YmdH');
        $count = Cache::increment($bucket) ?: 1;

        if ($count === 1) {
            Cache::put($bucket, 1, now()->addHours(2));
        }

        // Alert exactly once as the threshold is crossed.
        if ($count === self::ALERT_THRESHOLD) {
            $this->alert($count, $event->errorMessage);
        }
    }

    protected function alert(int $count, string $reason): void
    {
        $address = config('mail.from.address');

        Log::critical("[OmniSignal] {$count} conversion uploads failed this hour. Latest: {$reason}");

        if (! $address || config('mail.default') === 'log') {
            return;
        }

        Notification::route('mail', $address)->notify(
            new class($count, $reason) extends BaseNotification
            {
                use Notifiable;

                public function __construct(private int $count, private string $reason) {}

                /** @return array<int, string> */
                public function via(object $notifiable): array
                {
                    return ['mail'];
                }

                public function toMail(object $notifiable): MailMessage
                {
                    return (new MailMessage)
                        ->error()
                        ->subject('Conversion uploads are failing')
                        ->line("{$this->count} conversion uploads have failed in the last hour.")
                        ->line("Most recent reason: {$this->reason}")
                        ->line('Run `php artisan ad-conversions:test` to check every channel\'s credentials.');
                }
            }
        );
    }
}
