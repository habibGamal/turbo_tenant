<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Catalog;

use App\Core\Plugins\Contracts\LineReferenceCatalog;
use App\Plugins\ComboPackages\src\Models\Package;

final class PackageLineReferenceCatalog implements LineReferenceCatalog
{
    public function pluginId(): string
    {
        return 'combo-packages';
    }

    public function lineType(): string
    {
        return 'package';
    }

    public function label(): string
    {
        return 'باقات التوفير';
    }

    /**
     * @return array<int, string> id => display name, at most $limit entries
     */
    public function search(string $term, int $limit = 50): array
    {
        $query = Package::query();

        $trimmed = mb_trim($term);
        if ($trimmed !== '') {
            $query->where(function ($q) use ($trimmed) {
                $q->where('name', 'like', "%{$trimmed}%")
                    ->orWhere('name_ar', 'like', "%{$trimmed}%");
            });
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Package> $packages */
        $packages = $query->limit($limit)->get();

        $results = [];
        foreach ($packages as $pkg) {
            $results[(int) $pkg->id] = $this->displayName($pkg);
        }

        return $results;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string> id => display name for the ids that still exist
     */
    public function labelsFor(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Package> $packages */
        $packages = Package::whereIn('id', $ids)->get();

        $results = [];
        foreach ($packages as $pkg) {
            $results[(int) $pkg->id] = $this->displayName($pkg);
        }

        return $results;
    }

    private function displayName(Package $package): string
    {
        if (app()->getLocale() === 'ar' && filled($package->name_ar)) {
            return (string) $package->name_ar;
        }

        return (string) ($package->name ?: $package->name_ar);
    }
}
