<?php

namespace Ninja\DeviceTracker\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class Device
 *
 *
 * @mixin \Illuminate\Database\Query\Builder
 * @mixin Builder<ChangeHistory>
 *
 * @property int $id unsigned int
 * @property string $column string
 * @property string $old_value string|null
 * @property string $new_value string|null
 * @property Carbon $created_at datetime|null
 */
class ChangeHistory extends Model
{
    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'column',
        'old_value',
        'new_value',
    ];

    public function getTable(): string
    {
        return config('devices.history.table', 'laravel_devices_history');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
