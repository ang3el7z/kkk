<?php

declare(strict_types=1);

namespace VpnBot\Module\Maintenance;

final class GitMaintenanceRuntime implements MaintenanceRuntime
{
    public function currentBranch(): string
    {
        return trim((string) exec('git -C / rev-parse --abbrev-ref HEAD'));
    }

    public function branchStatusLines(): array
    {
        exec('git -C / branch -vv', $output);

        return $output;
    }
}
