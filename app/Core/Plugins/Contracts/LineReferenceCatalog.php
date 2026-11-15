<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

/**
 * Lets admin UIs in other plugins (coupons, POS) list the things a composite cart line
 * can refer to (cart_items.line_reference_id / order_items.line_reference_id) without
 * depending on the plugin that owns them. Collected with
 * PluginContributions->active('catalog.line-references').
 */
interface LineReferenceCatalog extends PluginContribution
{
    /** The line_type these references belong to, e.g. 'package'. */
    public function lineType(): string;

    /** Human label for the whole group, e.g. 'باقات التوفير'. */
    public function label(): string;

    /**
     * @return array<int, string> id => display name, at most $limit entries
     */
    public function search(string $term, int $limit = 50): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, string> id => display name for the ids that still exist
     */
    public function labelsFor(array $ids): array;
}
