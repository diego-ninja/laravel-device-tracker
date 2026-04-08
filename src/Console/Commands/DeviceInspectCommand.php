<?php

namespace Ninja\DeviceTracker\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Models\Device;

final class DeviceInspectCommand extends Command
{
    protected $signature = 'devices:inspect {uuid : Device UUID to inspect}';

    protected $description = 'Inspect detailed information about a specific device';

    public function handle(): void
    {
        $uuid = $this->argument('uuid');
        if (! is_string($uuid)) {
            $this->error('Invalid UUID provided');

            return;
        }

        try {
            $device = Device::byUuid($uuid);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return;
        }

        if ($device === null) {
            $this->error(sprintf('Device with UUID %s not found', $uuid));

            return;
        }

        $sessionsQuery = $device->sessions()->getQuery();
        $totalSessions = (clone $sessionsQuery)->count();
        $activeSessions = (clone $sessionsQuery)
            ->whereNull('finished_at')
            ->where('status', SessionStatus::Active->value)
            ->count();
        $associatedUsers = (clone $sessionsQuery)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        $this->info('Device Information:');
        $this->table(
            ['Property', 'Value'],
            [
                ['UUID', $device->uuid],
                ['Status', $device->status->value],
                ['Browser', $device->browser],
                ['Platform', $device->platform],
                ['Device Type', $device->device_type],
                ['IP', $device->ip],
                ['Created', $device->created_at],
                ['Last Updated', $device->updated_at],
                ['Active Sessions', $activeSessions],
                ['Total Sessions', $totalSessions],
                ['Associated Users', $associatedUsers],
            ]
        );
    }
}
