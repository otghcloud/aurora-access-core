<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Services\AccessControl\AccessControlConfigurationRegistry;
use Composer\InstalledVersions;
use Illuminate\View\View;

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
            if (! str_starts_with($packageName, 'otghcloud/access-')) {
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
