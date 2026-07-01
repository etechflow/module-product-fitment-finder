<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Plugin\Catalog\Layer;

use Magento\Catalog\Model\Layer;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Constrain a category's product collection to products compatible with the
 * vehicle in the URL (?make_id / model_id / year / part_id).
 *
 * We ADD an `entity_id IN (…)` filter to the EXISTING layer collection rather
 * than swapping in a fresh one. That is deliberate: on stores using
 * CatalogSearch / OpenSearch layered navigation the collection is a fulltext
 * collection, and Magento's own Category layer filter later calls
 * addFieldToFilter('category_ids_to_aggregate', …) on it. A replacement plain
 * EAV collection has no such field, so it threw
 * "The 'category_ids_to_aggregate' attribute name is invalid" → HTTP 500 on
 * every vehicle-filtered category page. Keeping the native collection type and
 * only narrowing it by entity_id avoids that entirely and still composes with
 * category filters, layered nav, sorting and pagination.
 *
 * The (make,model,year,part) → entity_id resolution scans vehicle_compat_data
 * once and is cached in Redis, tagged with the catalog product tag so it
 * auto-invalidates on any product save.
 */
class FilterByVehicle
{
    private const CACHE_TAG   = 'ETECHFLOW_VC_FILTER';
    private const CATALOG_TAG = 'cat_p';
    private const CACHE_TTL   = 86400;

    private bool $applied = false;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResourceConnection $resource,
        private readonly CacheInterface $cache
    ) {
    }

    public function afterGetProductCollection(Layer $subject, $collection)
    {
        // getProductCollection() is called several times per request; the Layer
        // caches one collection object, so apply the narrowing exactly once.
        if ($this->applied || !$this->hasVehicleParams()) {
            return $collection;
        }
        $this->applied = true;

        $allowed = $this->resolveAllowedIdsCached();
        // No matches → force an empty result set (never leave the filter unapplied).
        $collection->addFieldToFilter('entity_id', ['in' => $allowed ?: [0]]);

        return $collection;
    }

    private function hasVehicleParams(): bool
    {
        return ((int) $this->request->getParam('make_id') > 0)
            || ((int) $this->request->getParam('model_id') > 0)
            || ((int) $this->request->getParam('year') > 0)
            || ((int) $this->request->getParam('part_id') > 0);
    }

    private function resolveAllowedIdsCached(): array
    {
        $makeId  = (int) $this->request->getParam('make_id');
        $modelId = (int) $this->request->getParam('model_id');
        $year    = (int) $this->request->getParam('year');
        $partId  = (int) $this->request->getParam('part_id');

        $key = sprintf('etechflow_vc_catids_%d_%d_%d_%d', $makeId, $modelId, $year, $partId);
        $hit = $this->cache->load($key);
        if ($hit !== false && $hit !== null && $hit !== '') {
            $decoded = json_decode($hit, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $ids = $this->resolveAllowedIds($makeId, $modelId, $year, $partId);
        $this->cache->save(json_encode($ids), $key, [self::CACHE_TAG, self::CATALOG_TAG], self::CACHE_TTL);
        return $ids;
    }

    /**
     * @return int[] product entity ids matching the vehicle (AND part) selection
     */
    private function resolveAllowedIds(int $makeId, int $modelId, int $year, int $partId): array
    {
        $conn = $this->resource->getConnection();
        $ids  = null; // null = "no vehicle constraint yet"

        if ($makeId > 0 || $modelId > 0 || $year > 0) {
            $attrId = (int) $conn->fetchOne(
                "SELECT attribute_id FROM " . $this->resource->getTableName('eav_attribute')
                . " WHERE entity_type_id = 4 AND attribute_code = 'vehicle_compat_data'"
            );
            $matched = [];
            if ($attrId > 0) {
                $rows = $conn->fetchAll(
                    "SELECT entity_id, value FROM " . $this->resource->getTableName('catalog_product_entity_text')
                    . " WHERE attribute_id = ? AND value IS NOT NULL AND value <> ''",
                    [$attrId]
                );
                foreach ($rows as $r) {
                    $decoded = json_decode((string) $r['value'], true);
                    if (!is_array($decoded)) {
                        continue;
                    }
                    foreach ($decoded as $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }
                        if ($makeId > 0 && (int) ($entry['make_id'] ?? 0) !== $makeId) {
                            continue;
                        }
                        if ($modelId > 0 && (int) ($entry['model_id'] ?? 0) !== $modelId) {
                            continue;
                        }
                        if ($year > 0) {
                            $years = array_map('intval', (array) ($entry['years'] ?? []));
                            if (!in_array($year, $years, true)) {
                                continue;
                            }
                        }
                        $matched[(int) $r['entity_id']] = true;
                        break;
                    }
                }
            }
            $ids = array_keys($matched);
        }

        if ($partId > 0) {
            $partAttrId = (int) $conn->fetchOne(
                "SELECT attribute_id FROM " . $this->resource->getTableName('eav_attribute')
                . " WHERE entity_type_id = 4 AND attribute_code = 'parts_required'"
            );
            $partIds = [];
            if ($partAttrId > 0) {
                $rows = $conn->fetchAll(
                    "SELECT entity_id, value FROM " . $this->resource->getTableName('catalog_product_entity_varchar')
                    . " WHERE attribute_id = ? AND value IS NOT NULL AND value <> ''",
                    [$partAttrId]
                );
                foreach ($rows as $r) {
                    $set = array_map('intval', array_filter(explode(',', (string) $r['value']), 'strlen'));
                    if (in_array($partId, $set, true)) {
                        $partIds[] = (int) $r['entity_id'];
                    }
                }
            }
            // Intersect with the vehicle match (or use parts alone if no vehicle picked).
            $ids = ($ids === null) ? $partIds : array_values(array_intersect($ids, $partIds));
        }

        return $ids ?? [];
    }
}
