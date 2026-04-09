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

    Template::query()->create([
        'name' => 'email_footer',
        'model_type' => Template::class,
        'model_id' => null,
        'subject' => null,
        'message' => '<p>Custom Footer Token</p><p>{{user_name}}<br>{{user_email}}<br>Truckload Team<br>Pegasus Logistics Group</p><p>www.pegasuslogistics.com</p>',
    ]);

    $template = Template::query()->create([
        'name' => 'Daily Carrier Summary '.str()->random(6),
        'model_type' => ScheduledItem::class,
        'model_id' => null,
        'subject' => '{{carrier_name}} ({{carrier_short_code}}) schedule {{today}}',
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
        'outbound_location_ids' => [$pickupLocation->id],
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
        ->and($capturedSubject)->toContain($carrier->short_code)
        ->and($capturedSubject)->toContain('04/05/2026')
        ->and($capturedBody)->toContain('SHIP-SCHED-001')
        ->and($capturedBody)->toContain('background-color: #0b5394')
        ->and($capturedBody)->toContain('background-color: #00ff00')
        ->and($capturedBody)->toContain('Custom Footer Token')
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

test('scheduled-items:send-due only includes shipments from selected outbound locations', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-05 09:00:00'));

    $carrier = Carrier::factory()->create([
        'emails' => 'dispatch@example.com',
    ]);

    $includedPickupLocation = Location::factory()->pickup()->create();
    $excludedPickupLocation = Location::factory()->pickup()->create();
    $dcLocation = Location::factory()->distribution_center()->create();

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-INCLUDED-001',
        'status' => 'Pending',
        'pickup_location_id' => $includedPickupLocation->id,
        'dc_location_id' => $dcLocation->id,
        'carrier_id' => $carrier->id,
        'drop_date' => '2026-04-05',
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-EXCLUDED-001',
        'status' => 'Pending',
        'pickup_location_id' => $excludedPickupLocation->id,
        'dc_location_id' => $dcLocation->id,
        'carrier_id' => $carrier->id,
        'drop_date' => '2026-04-05',
    ]);

    $template = Template::query()->create([
        'name' => 'Outbound Filter Template '.str()->random(6),
        'model_type' => ScheduledItem::class,
        'model_id' => null,
        'subject' => 'Outbound filter',
        'message' => '<p>{{carrier_shipments}}</p>',
    ]);

    ScheduledItem::query()->create([
        'name' => 'Outbound Filtered Item',
        'schedule_type' => 'daily',
        'schedule_time' => '09:00:00',
        'template_id' => $template->id,
        'apply_to_all' => false,
        'schedulable_type' => Carrier::class,
        'schedulable_id' => $carrier->id,
        'outbound_location_ids' => [$includedPickupLocation->id],
    ]);

    $capturedBody = '';

    Mail::shouldReceive('send')
        ->once()
        ->withArgs(function ($view, $data, $callback) use (&$capturedBody): bool {
            $fakeMessage = new class
            {
                public function to(array $recipients): self
                {
                    return $this;
                }

                public function subject(string $subject): self
                {
                    return $this;
                }

                public string $htmlBody = '';

                public function html(string $body): self
                {
                    $this->htmlBody = $body;

                    return $this;
                }
            };

            $callback($fakeMessage);
            $capturedBody = $fakeMessage->htmlBody;

            return true;
        });

    $this->artisan('scheduled-items:send-due')
        ->assertExitCode(0);

    expect($capturedBody)->toContain('SHIP-INCLUDED-001')
        ->and($capturedBody)->not->toContain('SHIP-EXCLUDED-001');
});

test('scheduled-items:send-due skips carrier when selected outbound locations have no shipments', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-05 09:00:00'));

    $carrier = Carrier::factory()->create([
        'emails' => 'dispatch@example.com',
    ]);

    $selectedPickupLocation = Location::factory()->pickup()->create();
    $otherPickupLocation = Location::factory()->pickup()->create();
    $dcLocation = Location::factory()->distribution_center()->create();

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-NON-MATCHING-001',
        'status' => 'Pending',
        'pickup_location_id' => $otherPickupLocation->id,
        'dc_location_id' => $dcLocation->id,
        'carrier_id' => $carrier->id,
        'drop_date' => '2026-04-05',
    ]);

    $template = Template::query()->create([
        'name' => 'No Matching Shipments Template '.str()->random(6),
        'model_type' => ScheduledItem::class,
        'model_id' => null,
        'subject' => 'No matching shipments',
        'message' => '<p>{{carrier_shipments}}</p>',
    ]);

    ScheduledItem::query()->create([
        'name' => 'No Matching Shipments Item',
        'schedule_type' => 'daily',
        'schedule_time' => '09:00:00',
        'template_id' => $template->id,
        'apply_to_all' => false,
        'schedulable_type' => Carrier::class,
        'schedulable_id' => $carrier->id,
        'outbound_location_ids' => [$selectedPickupLocation->id],
    ]);

    Mail::shouldReceive('send')->never();

    $this->artisan('scheduled-items:send-due')
        ->assertExitCode(0);
});

test('scheduled-items:send-due renders one carrier shipment table per pickup location', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-05 09:00:00'));

    $carrier = Carrier::factory()->create([
        'emails' => 'dispatch@example.com',
    ]);

    $pickupLocationA = Location::factory()->pickup()->create();
    $pickupLocationB = Location::factory()->pickup()->create();
    $dcLocation = Location::factory()->distribution_center()->create();

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-LOC-A-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickupLocationA->id,
        'dc_location_id' => $dcLocation->id,
        'carrier_id' => $carrier->id,
        'drop_date' => '2026-04-05',
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-LOC-B-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickupLocationB->id,
        'dc_location_id' => $dcLocation->id,
        'carrier_id' => $carrier->id,
        'drop_date' => '2026-04-05',
    ]);

    $template = Template::query()->create([
        'name' => 'Per Location Tables Template '.str()->random(6),
        'model_type' => ScheduledItem::class,
        'model_id' => null,
        'subject' => 'Per location tables',
        'message' => '<p>{{carrier_shipments}}</p>',
    ]);

    ScheduledItem::query()->create([
        'name' => 'Per Location Tables Item',
        'schedule_type' => 'daily',
        'schedule_time' => '09:00:00',
        'template_id' => $template->id,
        'apply_to_all' => false,
        'schedulable_type' => Carrier::class,
        'schedulable_id' => $carrier->id,
        'outbound_location_ids' => [$pickupLocationA->id, $pickupLocationB->id],
    ]);

    $capturedBody = '';

    Mail::shouldReceive('send')
        ->once()
        ->withArgs(function ($view, $data, $callback) use (&$capturedBody): bool {
            $fakeMessage = new class
            {
                public function to(array $recipients): self
                {
                    return $this;
                }

                public function subject(string $subject): self
                {
                    return $this;
                }

                public string $htmlBody = '';

                public function html(string $body): self
                {
                    $this->htmlBody = $body;

                    return $this;
                }
            };

            $callback($fakeMessage);
            $capturedBody = $fakeMessage->htmlBody;

            return true;
        });

    $this->artisan('scheduled-items:send-due')
        ->assertExitCode(0);

    expect($capturedBody)->toContain('SHIP-LOC-A-001')
        ->and($capturedBody)->toContain('SHIP-LOC-B-001')
        ->and($capturedBody)->toContain($pickupLocationA->name)
        ->and($capturedBody)->toContain($pickupLocationB->name)
        ->and(substr_count($capturedBody, '<table style="border-collapse: collapse; width: 100%; border: 1px solid #000; margin-bottom: 16px;" border="1">'))->toBe(2);
});
