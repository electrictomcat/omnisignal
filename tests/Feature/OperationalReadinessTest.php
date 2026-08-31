<?php

namespace Tests\Feature;

use App\Listeners\ReportConversionFailure;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionUploadFailed;
use ElectricTomCat\GoogleAdsConversions\Jobs\UploadPendingConversions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The pipeline being correct is not the same as the pipeline running.
 */
class OperationalReadinessTest extends TestCase
{
    /** @return array<int, string> */
    private function scheduledSummaries(): array
    {
        return collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->description ?? $event->getSummaryForDisplay())
            ->all();
    }

    public function test_the_conversion_upload_job_is_scheduled(): void
    {
        // routes/console.php was the stock skeleton, so nothing flushed the
        // buffer or uploaded anything on a timer.
        $this->assertContains('omnisignal:upload-conversions', $this->scheduledSummaries());
    }

    public function test_retention_pruning_is_scheduled(): void
    {
        $this->assertContains('omnisignal:prune-leads', $this->scheduledSummaries());
    }

    public function test_the_upload_job_cannot_overlap_itself(): void
    {
        $this->assertInstanceOf(
            ShouldBeUnique::class,
            new UploadPendingConversions,
        );
    }

    public function test_an_upload_failure_reaches_a_listener(): void
    {
        Event::fake([ConversionUploadFailed::class]);

        ConversionUploadFailed::dispatch('gclid-x', 'Conversion action not found.', ['event' => 'Quote Form']);

        Event::assertDispatched(ConversionUploadFailed::class);
        Event::assertListening(ConversionUploadFailed::class, ReportConversionFailure::class);
    }

    public function test_the_failure_listener_logs_what_went_wrong(): void
    {
        Log::spy();

        (new ReportConversionFailure)->handle(
            new ConversionUploadFailed('gclid-x', 'Invalid gclid.', ['event' => 'Quote Form', 'order_id' => 'ORD-1'])
        );

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context) => str_contains($message, 'Conversion upload failed')
                && $context['reason'] === 'Invalid gclid.'
                && $context['order_id'] === 'ORD-1');
    }
}
