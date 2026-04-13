<?php

namespace App\Observers;

use App\Actions\SendNotification;
use App\Models\AutomatedItem;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MonitoredModelObserver
{
    public function updated(Model $model): void
    {
        if (! $this->isSupportedModel($model)) {
            return;
        }

        $changedAttributes = array_values(array_filter(
            array_keys($model->getChanges()),
            fn (string $attribute): bool => $attribute !== 'updated_at'
        ));

        if ($changedAttributes === []) {
            return;
        }

        /** @var Collection<int, AutomatedItem> $automatedItems */
        $automatedItems = AutomatedItem::query()
            ->where('is_active', true)
            ->where('monitorable_type', $model::class)
            ->get();

        foreach ($automatedItems as $automatedItem) {
            $relevantChanges = array_values(array_intersect(
                $automatedItem->monitored_fields ?? [],
                $changedAttributes
            ));

            if ($relevantChanges === []) {
                continue;
            }

            if ($model instanceof Shipment && $automatedItem->role_name === 'carrier') {
                $this->notifyShipmentCarrierUsers($automatedItem, $model, $relevantChanges);

                continue;
            }

            $subject = 'Automated Item Triggered: '.$automatedItem->name;
            $message = $this->buildMessage($model, $relevantChanges);

            SendNotification::toRole($automatedItem->role_name, $subject, $message);
        }
    }

    /**
     * @param  list<string>  $changedFields
     */
    private function notifyShipmentCarrierUsers(AutomatedItem $automatedItem, Shipment $shipment, array $changedFields): void
    {
        if (in_array('carrier_id', $changedFields, true)) {
            $this->notifyCarrierReassignment($shipment);

            return;
        }

        if (blank($shipment->carrier_id)) {
            return;
        }

        $carrierUsers = $this->carrierUsers((string) $shipment->carrier_id);

        if ($carrierUsers->isEmpty()) {
            return;
        }

        SendNotification::toMultipleUsers(
            $carrierUsers,
            'Automated Item Triggered: '.$automatedItem->name,
            $this->buildMessage($shipment, $changedFields)
        );
    }

    private function notifyCarrierReassignment(Shipment $shipment): void
    {
        $shipmentNumber = $shipment->shipment_number ?: $shipment->guid;
        $originalCarrierId = $shipment->getOriginal('carrier_id');
        $newCarrierId = $shipment->carrier_id;

        if (! blank($originalCarrierId)) {
            $originalCarrierUsers = $this->carrierUsers((string) $originalCarrierId);

            if ($originalCarrierUsers->isNotEmpty()) {
                SendNotification::toMultipleUsers(
                    $originalCarrierUsers,
                    "Stand Down: Shipment {$shipmentNumber}",
                    "Shipment {$shipmentNumber} is no longer assigned to your carrier. Please stand down on this shipment."
                );
            }
        }

        if (! blank($newCarrierId)) {
            $newCarrierUsers = $this->carrierUsers((string) $newCarrierId);

            if ($newCarrierUsers->isNotEmpty()) {
                $newCarrierName = $this->carrierName((string) $newCarrierId);

                SendNotification::toMultipleUsers(
                    $newCarrierUsers,
                    "New Shipment Assigned: {$shipmentNumber}",
                    "Shipment {$shipmentNumber} has been assigned to {$newCarrierName}. Please review the shipment details."
                );
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function carrierUsers(string $carrierId): Collection
    {
        return User::role('carrier')
            ->where('carrier_id', $carrierId)
            ->get();
    }

    private function carrierName(string $carrierId): string
    {
        $carrier = Carrier::query()->find($carrierId);

        if ($carrier === null) {
            return 'the assigned carrier';
        }

        return $carrier->name ?: $carrier->short_code ?: 'the assigned carrier';
    }

    private function isSupportedModel(Model $model): bool
    {
        return $model instanceof Shipment
            || $model instanceof Location
            || $model instanceof Carrier;
    }

    /**
     * @param  list<string>  $changedFields
     */
    private function buildMessage(Model $model, array $changedFields): string
    {
        $modelLabel = AutomatedItem::labelForClass($model::class);
        $recordIdentifier = $this->recordIdentifier($model);

        $lines = collect($changedFields)
            ->map(function (string $field) use ($model): string {
                $oldValue = $this->stringifyFieldValue($field, $model->getOriginal($field));
                $newValue = $this->stringifyFieldValue($field, $model->getAttribute($field));

                return sprintf('%s: %s -> %s', $field, $oldValue, $newValue);
            })
            ->implode("\n");

        return "Monitored {$modelLabel} record {$recordIdentifier} has changes:\n{$lines}";
    }

    private function recordIdentifier(Model $model): string
    {
        if ($model instanceof Shipment) {
            return $model->shipment_number ?: $model->guid;
        }

        if ($model instanceof Location || $model instanceof Carrier) {
            return $model->short_code ?: $model->name ?: (string) $model->getKey();
        }

        return (string) $model->getKey();
    }

    private function stringifyFieldValue(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        $foreignKeyLabel = $this->resolveForeignKeyLabel($field, $value);

        if ($foreignKeyLabel !== null) {
            return $foreignKeyLabel;
        }

        if ($value instanceof DateTimeInterface) {
            return $this->formatDateValue($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return $this->formatScalarValue($value);
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'unserializable';
    }

    private function formatScalarValue(string|int|float|bool $value): string
    {
        if (is_string($value)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return Carbon::parse($value)
                    ->timezone('America/Chicago')
                    ->format('M j, Y')
                    .' CST';
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?/', $value) === 1) {
                try {
                    return $this->formatDateValue(Carbon::parse($value));
                } catch (\Throwable) {
                    return $value;
                }
            }

            return $value;
        }

        return (string) $value;
    }

    private function formatDateValue(DateTimeInterface $value): string
    {
        return Carbon::instance($value)
            ->timezone('America/Chicago')
            ->format('M j, Y g:i A')
            .' CST';
    }

    private function resolveForeignKeyLabel(string $field, mixed $value): ?string
    {
        if (! str_ends_with($field, '_id') || blank($value)) {
            return null;
        }

        $idValue = (string) $value;

        return match ($field) {
            'carrier_id' => $this->carrierName($idValue),
            'pickup_location_id', 'dc_location_id', 'recycling_location_id' => $this->locationName($idValue),
            default => null,
        };
    }

    private function locationName(string $locationId): ?string
    {
        $location = Location::query()->find($locationId);

        if ($location === null) {
            return null;
        }

        if (! empty($location->short_code) && ! empty($location->name)) {
            return "{$location->short_code} - {$location->name}";
        }

        return $location->name ?: $location->short_code ?: null;
    }
}
