<?php
declare(strict_types=1);

namespace VpnBot\Module\Maintenance;

interface MaintenanceRuntime
{
    public function currentBranch(): string;

    /**
     * @return list<string>
     */
    public function branchStatusLines(): array;
}
