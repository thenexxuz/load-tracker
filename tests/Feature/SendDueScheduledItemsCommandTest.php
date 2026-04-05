<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\ScheduledItem;
use App\Models\Shipment;
use App\Models\Template;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

test('scheduled-items:send-due sends email for due daily scheduled item', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-05 09:00:00'));

    config()->set('mail.from.name', 'Scheduled Sender');
    config()->set('mail.from.address', 'scheduled@example.com');

    $carrier = Carrier::factory()->create([
        'emails' => 'dispatch@example.com;logistics@example.com',
    ]);

    $pickupLocation = Location::factory()->pickup()->create();
    $dcLocation = Location::factory()->distribution_center()->create();

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-SCHED-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickupLocation->id,
        'dc_location_id' => $dcLocation->id,
        'carrier_id' => $carrier->id,
        'drop_date' => '2026-04-05',
        'trailer' => null,
    ]);

    $template = Template::query()->create([
        'name' => 'Daily Carrier Summary '.str()->random(6),
        'model_type' => ScheduledItem::class,
        'model_id' => null,
        'subject' => '{{carrier_name}} schedule {{today}}',
        'message' => '<p>{{carrier_shipments}}</p><p>{{email_footer}}</p>',
    ]);

    ScheduledItem::query()->create([
        'name' => '9am Carrier Summary',
        'schedule_type' => 'daily',
        'schedule_time' => '09:00:00',
        'template_id' => $template->id,
        'apply_to_all' => false,
        'schedulable_type' => Carrier::class,
        'schedulable_id' => $carrier->id,
    ]);

    $capturedSubject = '';
    $capturedBody = '';
    $capturedRecipients = [];

    Mail::shouldReceive('send')
        ->once()
        ->withArgs(function ($view, $data, $callback) use (&$capturedSubject, &$capturedBody, &$capturedRecipients): bool {
            $fakeMessage = new class
            {
                public array $to = [];

                public string $subjectLine = '';

                public string $htmlBody = '';

                public function to(array $recipients): self
                {
                    $this->to = $recipients;

                    return $this;
                }

                public function subject(string $subject): self
                {
                    $this->subjectLine = $subject;

                    return $this;
                }

                public function html(string $body): self
                {
                    $this->htmlBody = $body;

                    return $this;
                }
            };

            $callback($fakeMessage);

            $capturedRecipients = $fakeMessage->to;
            $capturedSubject = $fakeMessage->subjectLine;
            $capturedBody = $fakeMessage->htmlBody;

            return true;
        });

    $this->artisan('scheduled-items:send-due')
        ->assertExitCode(0);

    expect($capturedRecipients)->toBe(['dispatch@example.com', 'logistics@example.com'])
        ->and($capturedSubject)->toContain($carrier->name)
        ->and($capturedSubject)->toContain('2026-04-05')
        ->and($capturedBody)->toContain('SHIP-SCHED-001')
        ->and($capturedBody)->toContain('background-color: #0b5394')
        ->and($capturedBody)->toContain('background-color: #00ff00')
        ->and($capturedBody)->toContain('Scheduled Sender')
        ->and($capturedBody)->toContain('scheduled@example.com')
        ->and($capturedBody)->toContain('Truckload Team')
        ->and($capturedBody)->toContain('Pegasus Logistics Group')
        ->and($capturedBody)->toContain('www.pegasuslogistics.com');
});

test('scheduled-items:send-due skips items that are not due this minute', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-05 09:00:00'));

    $carrier = Carrier::factory()->create([
        'emails' => 'dispatch@example.com',
    ]);

    $template = Template::query()->create([
        'name' => 'Not Due Template '.str()->random(6),
        'model_type' => ScheduledItem::class,
        'model_id' => null,
        'subject' => 'Not due',
        'message' => '<p>Not due</p>',
    ]);

    ScheduledItem::query()->create([
        'name' => 'Not Due Item',
        'schedule_type' => 'daily',
        'schedule_time' => '09:01:00',
        'template_id' => $template->id,
        'apply_to_all' => false,
        'schedulable_type' => Carrier::class,
        'schedulable_id' => $carrier->id,
    ]);

    Mail::shouldReceive('send')->never();

    $this->artisan('scheduled-items:send-due')
        ->assertExitCode(0);
});
