<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

test('send paperwork page is accessible by get route', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-PAPER-GET',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.shipments.send-paperwork', $shipment->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipments/SendPaperwork')
            ->where('shipment.id', $shipment->guid)
        );
});

test('send paperwork post route validates template id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-PAPER-POST',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.shipments.send-paperwork', $shipment->guid))
        ->post(route('admin.shipments.send-paperwork.process', $shipment->guid), [])
        ->assertRedirect(route('admin.shipments.send-paperwork', $shipment->guid))
        ->assertSessionHasErrors(['template_id']);
});

test('send paperwork post route sends successfully with array carrier emails', function (): void {
    Mail::shouldReceive('send')->once()->andReturnNull();

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();
    $carrier = Carrier::factory()->create([
        'emails' => ['dispatch@example.com', '"Ops" <ops@example.com>'],
    ]);

    $template = Template::query()->create([
        'name' => 'Paperwork Template',
        'model_type' => 'App\\Models\\Location',
        'model_id' => $pickup->id,
        'subject' => 'Shipment {{shipment_number}} paperwork',
        'message' => 'Documents for {{pickup_location}} to {{dc_location}}.',
    ]);

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-PAPER-SUCCESS',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $carrier->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.shipments.send-paperwork.process', $shipment->guid), [
            'template_id' => $template->id,
        ])
        ->assertRedirect(route('admin.shipments.show', $shipment->guid))
        ->assertSessionHas('success', 'Paperwork sent successfully.');

    expect($shipment->fresh()?->paperwork_sent)->not->toBeNull();
});

test('send paperwork post route ignores malformed emails and sends to valid recipients only', function (): void {
    $capturedRecipients = [];

    Mail::shouldReceive('send')
        ->once()
        ->withArgs(function ($view, $data, $callback) use (&$capturedRecipients): bool {
            $fakeMessage = new class
            {
                public array $to = [];

                public function to(array $recipients): self
                {
                    $this->to = $recipients;

                    return $this;
                }

                public function subject(string $subject): self
                {
                    return $this;
                }

                public function replyTo(string $email, ?string $name = null): self
                {
                    return $this;
                }

                public function html(string $body): self
                {
                    return $this;
                }

                public function attach(string $path, array $options = []): self
                {
                    return $this;
                }
            };

            $callback($fakeMessage);
            $capturedRecipients = $fakeMessage->to;

            return true;
        })
        ->andReturnNull();

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();
    $carrier = Carrier::factory()->create([
        'emails' => [
            'dispatch@example.com',
            'not-an-email',
            '"Ops" <ops@example.com>',
            'ops@example.com',
            '<invalid@>',
            '',
        ],
    ]);

    $template = Template::query()->create([
        'name' => 'Paperwork Template - Filtering',
        'model_type' => 'App\\Models\\Location',
        'model_id' => $pickup->id,
        'subject' => 'Shipment {{shipment_number}} paperwork',
        'message' => 'Documents for {{pickup_location}} to {{dc_location}}.',
    ]);

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-PAPER-MIXED',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $carrier->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.shipments.send-paperwork.process', $shipment->guid), [
            'template_id' => $template->id,
        ])
        ->assertRedirect(route('admin.shipments.show', $shipment->guid))
        ->assertSessionHas('success', 'Paperwork sent successfully.');

    expect($capturedRecipients)->toBe(['dispatch@example.com', 'ops@example.com']);
    expect($shipment->fresh()?->paperwork_sent)->not->toBeNull();
});

test('send paperwork includes all consolidation shipments in table placeholder', function (): void {
    $capturedBody = '';

    Mail::shouldReceive('send')
        ->once()
        ->withArgs(function ($view, $data, $callback) use (&$capturedBody): bool {
            $fakeMessage = new class
            {
                public array $to = [];

                public string $htmlBody = '';

                public function to(array $recipients): self
                {
                    $this->to = $recipients;

                    return $this;
                }

                public function subject(string $subject): self
                {
                    return $this;
                }

                public function replyTo(string $email, ?string $name = null): self
                {
                    return $this;
                }

                public function html(string $body): self
                {
                    $this->htmlBody = $body;

                    return $this;
                }

                public function attach(string $path, array $options = []): self
                {
                    return $this;
                }
            };

            $callback($fakeMessage);
            $capturedBody = $fakeMessage->htmlBody;

            return true;
        })
        ->andReturnNull();

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();
    $carrier = Carrier::factory()->create([
        'emails' => ['dispatch@example.com'],
    ]);

    $consolidationNumber = 'CONSOL-TEST-001';

    $primaryShipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-CONSOL-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $carrier->id,
        'consolidation_number' => $consolidationNumber,
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-CONSOL-002',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $carrier->id,
        'consolidation_number' => $consolidationNumber,
    ]);

    $template = Template::query()->create([
        'name' => 'Consolidation Table Template',
        'model_type' => 'App\\Models\\Location',
        'model_id' => $pickup->id,
        'subject' => 'Shipment {{shipment_number}} paperwork',
        'message' => '{{shipment_info_table}}',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.shipments.send-paperwork.process', $primaryShipment->guid), [
            'template_id' => $template->id,
        ])
        ->assertRedirect(route('admin.shipments.show', $primaryShipment->guid))
        ->assertSessionHas('success', 'Paperwork sent successfully.');

    expect($capturedBody)->toContain('SHIP-CONSOL-001');
    expect($capturedBody)->toContain('SHIP-CONSOL-002');
    expect(substr_count($capturedBody, 'SHIP-CONSOL-001'))->toBe(1);
    expect(substr_count($capturedBody, 'SHIP-CONSOL-002'))->toBe(1);
});

test('send paperwork replaces user placeholder tokens', function (): void {
    $capturedSubject = '';
    $capturedBody = '';
    $capturedReplyToEmail = '';
    $capturedReplyToName = '';

    Mail::shouldReceive('send')
        ->once()
        ->withArgs(function ($view, $data, $callback) use (&$capturedSubject, &$capturedBody, &$capturedReplyToEmail, &$capturedReplyToName): bool {
            $fakeMessage = new class
            {
                public string $subjectLine = '';

                public string $htmlBody = '';

                public string $replyToEmail = '';

                public string $replyToName = '';

                public function to(array $recipients): self
                {
                    return $this;
                }

                public function subject(string $subject): self
                {
                    $this->subjectLine = $subject;

                    return $this;
                }

                public function replyTo(string $email, ?string $name = null): self
                {
                    $this->replyToEmail = $email;
                    $this->replyToName = $name ?? '';

                    return $this;
                }

                public function html(string $body): self
                {
                    $this->htmlBody = $body;

                    return $this;
                }

                public function attach(string $path, array $options = []): self
                {
                    return $this;
                }
            };

            $callback($fakeMessage);
            $capturedSubject = $fakeMessage->subjectLine;
            $capturedBody = $fakeMessage->htmlBody;
            $capturedReplyToEmail = $fakeMessage->replyToEmail;
            $capturedReplyToName = $fakeMessage->replyToName;

            return true;
        })
        ->andReturnNull();

    $admin = User::factory()->create([
        'name' => 'Template Sender',
        'email' => 'sender@example.com',
    ]);
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();
    $carrier = Carrier::factory()->create([
        'emails' => ['dispatch@example.com'],
    ]);

    $template = Template::query()->create([
        'name' => 'User Placeholder Template',
        'model_type' => 'App\\Models\\Location',
        'model_id' => $pickup->id,
        'subject' => 'Sent by {{user_name}}',
        'message' => 'Contact {{user_name}} at {{user_email}}',
    ]);

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-PAPER-USER-TOKENS',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $carrier->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.shipments.send-paperwork.process', $shipment->guid), [
            'template_id' => $template->id,
        ])
        ->assertRedirect(route('admin.shipments.show', $shipment->guid))
        ->assertSessionHas('success', 'Paperwork sent successfully.');

    expect($capturedSubject)->toContain('Template Sender');
    expect($capturedSubject)->not->toContain('{{user_name}}');
    expect($capturedReplyToEmail)->toBe('sender@example.com');
    expect($capturedReplyToName)->toBe('Template Sender');
    expect($capturedBody)->toContain('Template Sender');
    expect($capturedBody)->toContain('sender@example.com');
    expect($capturedBody)->not->toContain('{{user_email}}');
});
