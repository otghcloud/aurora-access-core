<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\System;

use Composer\InstalledVersions;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlConfigurationRegistry;

class EnvironmentController extends Controller
{
    public function __invoke(AccessControlConfigurationRegistry $registry): View
    {
        return view('admin.system.environment', [
            'installedAccessPackages' => $this->resolveInstalledAccessPackages(),
            'registeredConfigSections' => $registry->groupedFields(),
        ]);
    }

    /**
     * @return array<int,array{name:string,version:string}>
     */
    private function resolveInstalledAccessPackages(): array
    {
        if (! class_exists(InstalledVersions::class)) {
            return [];
        }

        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $packageName) {
            if (! str_starts_with($packageName, 'otghcloud/aurora-access-')) {
                continue;
            }

            $packages[] = [
                'name' => $packageName,
                'version' => InstalledVersions::getPrettyVersion($packageName) ?? 'unknown',
            ];
        }

        usort($packages, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $packages;
    }
}
