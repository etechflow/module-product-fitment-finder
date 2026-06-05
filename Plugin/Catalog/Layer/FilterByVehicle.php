<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Plugin\Catalog\Layer;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * When the URL has vehicle params, REPLACE the category Layer's product
 * collection with a standard filtered Catalog\Product\Collection. The
 * expensive JSON scan that resolves (make,model,year) -> entity_ids is
 * cached in Redis (tagged with CATALOG_PRODUCT so it auto-invalidates on
 * any product save).
 */
class FilterByVehicle
{
    private const CACHE_TAG = 'ETECHFLOW_VC_FILTER';
    private const CACHE_TTL = 86400;
    private const CATALOG_TAG = 'cat_p';

    private RequestInterface $request;
    private ResourceConnection $resource;
    private CollectionFactory $collectionFactory;
    private StoreManagerInterface $storeManager;
    private CacheInterface $cache;
    private ?Collection $replacement = null;

    public function __construct(
        RequestInterface $request,
        ResourceConnection $resource,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        CacheInterface $cache
    ) {
        $this->request = $request;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->cache = $cache;
    }

    public function afterGetProductCollection(Layer $subject, $collection)
    {
        if (!$this->hasVehicleParams()) {
            return $collection;
        }
        if ($this->replacement === null) {
            $categoryId = 0;
            try {
                $cat = $subject->getCurrentCategory();
                if ($cat) $categoryId = (int) $cat->getId();
            } catch (\Throwable $e) {}
            $this->replacement = $this->buildFiltered($categoryId);
        }
        return $this->replacement;
    }

    private function hasVehicleParams(): bool
    {
        return ((int)$this->request->getParam('make_id') > 0)
            || ((int)$this->request->getParam('model_id') > 0)
            || ((int)$this->request->getParam('year') > 0)
            || ((int)$this->request->getParam('part_id') > 0);
    }

    private function buildFiltered(int $categoryId): Collection
    {
        $makeId  = (int) $this->request->getParam('make_id');
        $modelId = (int) $this->request->getParam('model_id');
        $year    = (int) $this->request->getParam('year');
        $partId  = (int) $this->request->getParam('part_id');

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect([
            'name','small_image','image','thumbnail','price','special_price','url_key',
            'status','visibility','short_description','sku','tax_class_id'
        ]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility([Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH]);
        $collection->setStoreId($this->storeManager->getStore()->getId());
        $collection->addMinimalPrice();
        $collection->addFinalPrice();
        $collection->addTaxPercents();
        $collection->addUrlRewrite();

        if ($categoryId > 0) {
            $collection->addCategoriesFilter(['in' => [$categoryId]]);
        }
        if ($partId > 0) {
            $collection->addAttributeToFilter('parts_required', ['finset' => (string)$partId]);
        }

        $usePrecise = ($year > 0 || ($makeId > 0 && $modelId > 0));
        if ($usePrecise) {
            $allowed = $this->loadAllowedEntityIdsCached($makeId, $modelId, $year);
            if (empty($allowed)) {
                $collection->addFieldToFilter('entity_id', ['eq' => 0]);
            } else {
                $collection->addFieldToFilter('entity_id', ['in' => $allowed]);
            }
        } else {
            if ($makeId > 0) {
                $collection->addAttributeToFilter('vehicle_compat_data', ['like' => '%"make_id":' . $makeId . ',%']);
            }
            if ($modelId > 0) {
                $collection->addAttributeToFilter('vehicle_compat_data', ['like' => '%"model_id":' . $modelId . ',%']);
            }
        }
        return $collection;
    }

    private function loadAllowedEntityIdsCached(int $makeId, int $modelId, int $year): array
    {
        $key = sprintf('etechflow_vc_ids_%d_%d_%d', $makeId, $modelId, $year);
        $hit = $this->cache->load($key);
        if ($hit !== false && $hit !== null && $hit !== '') {
            $decoded = json_decode($hit, true);
            if (is_array($decoded)) return $decoded;
        }
        $ids = $this->loadAllowedEntityIds($makeId, $modelId, $year);
        $this->cache->save(json_encode($ids), $key, [self::CACHE_TAG, self::CATALOG_TAG], self::CACHE_TTL);
        return $ids;
    }

    private function loadAllowedEntityIds(int $makeId, int $modelId, int $year): array
    {
        $conn = $this->resource->getConnection();
        $attrId = (int) $conn->fetchOne(
            "SELECT attribute_id FROM " . $this->resource->getTableName('eav_attribute')
            . " WHERE entity_type_id = 4 AND attribute_code = 'vehicle_compat_data'"
        );
        if ($attrId <= 0) return [];
        $rows = $conn->fetchAll(
            "SELECT entity_id, value FROM " . $this->resource->getTableName('catalog_product_entity_text')
            . " WHERE attribute_id = ?",
            [$attrId]
        );
        $allowed = [];
        foreach ($rows as $r) {
            $decoded = json_decode((string)$r['value'], true);
            if (!is_array($decoded)) continue;
            foreach ($decoded as $entry) {
                if (!is_array($entry)) continue;
                if ($makeId > 0 && (int)($entry['make_id'] ?? 0) !== $makeId) continue;
                if ($modelId > 0 && (int)($entry['model_id'] ?? 0) !== $modelId) continue;
                if ($year > 0) {
                    $years = array_map('intval', (array)($entry['years'] ?? []));
                    if (!in_array($year, $years, true)) continue;
                }
                $allowed[] = (int)$r['entity_id']; break;
            }
        }
        return $allowed;
    }
}
