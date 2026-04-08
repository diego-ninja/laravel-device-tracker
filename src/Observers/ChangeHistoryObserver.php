<?php

namespace Ninja\DeviceTracker\Observers;

use Illuminate\Database\Eloquent\Model;
use Ninja\DeviceTracker\Models\ChangeHistory;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;

final class ChangeHistoryObserver
{
    public function updated(Model $model): void
    {
        if (config('devices.history.enabled', false) && ! $model->wasRecentlyCreated) {
            $attributes = config(sprintf('devices.history.models.%s', self::getModelKey($model)), []);
            foreach ($attributes as $attribute) {
                if (! $model->originalIsEquivalent($attribute)) {
                    $newHistory = new ChangeHistory([
                        'column' => $attribute,
                        'old_value' => $this->getStringValue($model->getOriginal($attribute)),
                        'new_value' => $this->getStringValue($model->getAttributeValue($attribute)),
                    ]);
                    $newHistory->model()->associate($model);
                    $newHistory->save();
                }
            }
        }
    }

    public function deleting(Model $model): void
    {
        if (config('devices.history.enabled', false) && method_exists($model, 'history')) {
            $model->history()->delete();
        }
    }

    public static function getModelKey(Model $model): string
    {
        if ($model instanceof Device) {
            return 'device';
        }

        if ($model instanceof Session) {
            return 'session';
        }

        throw new \InvalidArgumentException('Unsupported model: '.get_class($model));
    }

    private function getStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return strval($value);
        }

        if (method_exists($value, '__toString')) {
            return $value->__toString();
        }

        $encoded = json_encode($value);

        return $encoded === false ? null : $encoded;
    }
}
