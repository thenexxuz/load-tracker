<?php

namespace App\Console\Commands;

use App\Models\Carrier;
use App\Models\ScheduledItem;
use App\Models\Shipment;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDueScheduledItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheduled-items:send-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send emails for scheduled items that are due right now';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now(config('app.timezone'));
        $sentCount = 0;

        $scheduledItems = ScheduledItem::query()
            ->with('template')
            ->whereNotNull('template_id')
            ->get();

        if ($scheduledItems->isEmpty()) {
            $this->info('No scheduled items are due at this minute.');

            return self::SUCCESS;
        }

        foreach ($scheduledItems as $item) {
            if (! $item instanceof ScheduledItem) {
                continue;
            }

            if (! $this->isDueNow($item, $now)) {
                continue;
            }

            $carriers = $this->resolveCarriers($item);

            foreach ($carriers as $carrier) {
                $recipients = $this->extractRecipients($carrier->emails);

                if ($recipients === []) {
                    continue;
                }

                $replacements = [
                    'carrier_name' => $carrier->name ?? '',
                    'today' => $now->format('Y-m-d'),
                    'user_name' => (string) (config('mail.from.name') ?: config('app.name')),
                    'user_email' => (string) (config('mail.from.address') ?: ''),
                ];

                $replacements['carrier_shipments'] = $this->buildCarrierShipmentsTable($carrier, $now);
                $replacements['email_footer'] = $this->buildEmailFooter($replacements);

                $subjectTemplate = $item->template?->subject ?: $item->name;
                $bodyTemplate = $item->template?->message ?: '';

                $subject = $this->applyTemplateReplacements($subjectTemplate, $replacements);
                $body = $this->applyTemplateReplacements($bodyTemplate, $replacements);

                try {
                    Mail::send([], [], function ($message) use ($recipients, $subject, $body): void {
                        $message->to($recipients);
                        $message->subject($subject);
                        $message->html($body);
                    });

                    $sentCount++;
                } catch (Throwable $exception) {
                    $this->error("Failed to send scheduled item #{$item->id} for carrier {$carrier->id}: {$exception->getMessage()}");
                }
            }
        }

        $this->info("Scheduled emails sent: {$sentCount}");

        return self::SUCCESS;
    }

    private function isDueNow(ScheduledItem $item, CarbonInterface $now): bool
    {
        $scheduledTime = substr((string) $item->schedule_time, 0, 5);

        if ($scheduledTime !== $now->format('H:i')) {
            return false;
        }

        if ($item->schedule_type === 'weekly') {
            return (int) $item->schedule_day_of_week === (int) $now->format('w');
        }

        if ($item->schedule_type === 'monthly') {
            return (int) $item->schedule_day_of_month === (int) $now->format('j');
        }

        return $item->schedule_type === 'daily';
    }

    /**
     * @return \Illuminate\Support\Collection<int, Carrier>
     */
    private function resolveCarriers(ScheduledItem $item)
    {
        if ($item->apply_to_all) {
            return Carrier::query()->get();
        }

        if ($item->schedulable_type !== Carrier::class || ! $item->schedulable_id) {
            return collect();
        }

        return Carrier::query()
            ->whereKey($item->schedulable_id)
            ->get();
    }

    /**
     * @param  array<int, string>|string|null  $rawEmails
     * @return array<int, string>
     */
    private function extractRecipients(array|string|null $rawEmails): array
    {
        if (empty($rawEmails)) {
            return [];
        }

        $parts = is_array($rawEmails)
            ? array_map('trim', $rawEmails)
            : array_map('trim', explode(';', str_replace(',', ';', (string) $rawEmails)));

        $emails = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/<([^>]+)>/', $part, $matches) === 1) {
                $email = trim($matches[1]);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }

                continue;
            }

            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $part;
            }
        }

        return array_values(array_unique($emails));
    }

    private function applyTemplateReplacements(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace('{{'.$key.'}}', (string) $value, $content);
        }

        return $content;
    }

    private function buildCarrierShipmentsTable(Carrier $carrier, CarbonInterface $now): string
    {
        $shipments = Shipment::query()
            ->with(['pickupLocation', 'dcLocation'])
            ->where('carrier_id', $carrier->id)
            ->whereRaw('LOWER(COALESCE(status, "")) NOT IN (?, ?)', ['delivered', 'cancelled'])
            ->orderBy('drop_date')
            ->orderBy('shipment_number')
            ->get();

        if ($shipments->isEmpty()) {
            return '<p>No active shipments found.</p>';
        }

        $headers = [
            'Status',
            'BOL',
            'Pickup Location',
            'Shipment Number',
            'DC Location',
            'Drop Date',
            'Pickup Date',
            'Delivery Date',
            'PO #',
            'Rack Qty',
            'Carrier',
            'Trailer',
            'Load Bars',
            'Straps',
            'Delivery Address',
        ];

        $html = <<<'HTML'
<table style="border-collapse: collapse; width: 100%; border: 1px solid #000;" border="1">
    <tbody>
        <tr style="background-color: #0b5394; color: #ecf0f1; text-align: center;">
HTML;

        foreach ($headers as $header) {
            $html .= '<td style="padding-left: 5px; padding-right: 5px;"><strong>'.e($header).'</strong></td>';
        }

        $html .= '</tr>';

        foreach ($shipments as $shipment) {
            $dropDate = $shipment->drop_date?->copy()?->timezone(config('app.timezone'));
            $isCurrentDropDay = $dropDate?->isSameDay($now) ?? false;
            $isMissingTrailer = blank($shipment->trailer) && blank($shipment->trailer_id);
            $backgroundColor = $isCurrentDropDay && $isMissingTrailer ? '#00ff00' : '#fff';

            $html .= '<tr style="border-color: #000; background-color: '.$backgroundColor.'; color: #000;">';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($shipment->status ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($shipment->bol ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) (optional($shipment->pickupLocation)->short_code ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($shipment->shipment_number ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) (optional($shipment->dcLocation)->short_code ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e($shipment->drop_date ? $shipment->drop_date->copy()->timezone(config('app.timezone'))->format('m/d/Y') : '').'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e($shipment->pickup_date ? $shipment->pickup_date->copy()->timezone(config('app.timezone'))->format('m/d/Y') : '').'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e($shipment->delivery_date ? $shipment->delivery_date->copy()->timezone(config('app.timezone'))->format('m/d/Y') : '').'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($shipment->po_number ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($shipment->rack_qty ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($carrier->short_code ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($shipment->trailer ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($shipment->load_bar_qty ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) ($shipment->strap_qty ?? '')).'</td>';
            $html .= '<td style="padding-left: 5px; padding-right: 5px;">'.e((string) (optional($shipment->dcLocation)->fullAddress() ?? '')).'</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function buildEmailFooter(array $replacements): string
    {
        return implode('', [
            '<p>Thank you!</p>',
            '<p>&nbsp;</p>',
            '<p>'.e((string) ($replacements['user_name'] ?? '')).'<br>',
            e((string) ($replacements['user_email'] ?? '')).'<br>',
            'Truckload Team<br>',
            'Pegasus Logistics Group</p>',
            '<p>&nbsp;</p>',
            '<p>306 Airline Drive<br>Coppell, TX 75019</p>',
            '<p>Tell Us How We&apos;re Doing</p>',
            '<p>www.pegasuslogistics.com</p>',
        ]);
    }
}
