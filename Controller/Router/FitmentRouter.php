<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Router;

use ETechFlow\ProductFitmentFinder\Model\Config;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\RouterInterface;

/**
 * SEO-friendly URL router for the Part Finder.
 *
 * Matches paths of the form:
 *   /<prefix>/<make-slug>
 *   /<prefix>/<make-slug>/<model-slug>
 *   /<prefix>/<make-slug>/<model-slug>/<year>
 *   /<prefix>/<make-slug>/<model-slug>/<year>/<part-slug>
 *
 * where `<prefix>` is the admin-configured SEO URL prefix (default "parts")
 * and the slugs are lowercase-kebab versions of the Make/Model/Part names.
 *
 * On match:
 *   - Slugs are resolved back to IDs via case-insensitive name lookup
 *   - Request params (make, model, year, part) are set
 *   - Request is forwarded to vehiclecompat/find/index
 *
 * Google and humans see /parts/bmw/3-series/2020/brake-pads instead of
 * /vehiclecompat/find/index?make=42&model=87&year=2020&part=12. Better
 * SEO, better social-share previews, better crawlability.
 *
 * Opt-in via admin (default off so existing installs keep their
 * query-string URLs working without surprise). When enabled, BOTH old
 * query-string URLs and new path-based URLs keep working — old links
 * don't break.
 */
class FitmentRouter implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Config $config,
        private readonly ResourceConnection $resource
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$this->config->isEnabled() || !$this->config->isSeoUrlsEnabled()) {
            return null;
        }

        // Strip any query string from the path.
        $pathRaw = (string) $request->getPathInfo();
        $path = trim(parse_url($pathRaw, PHP_URL_PATH) ?: $pathRaw, '/');
        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $prefix = $this->config->getSeoUrlPrefix();
        if ($prefix === '' || ($segments[0] ?? '') !== $prefix) {
            return null;
        }

        // Strip prefix; remaining = make/model/year/part
        array_shift($segments);
        if ($segments === []) {
            return null;
        }
        if (count($segments) > 4) {
            // Beyond our schema — let other routers try
            return null;
        }

        [$makeSlug, $modelSlug, $yearSeg, $partSlug] = array_pad($segments, 4, null);

        // Resolve Make slug → ID. Required.
        $makeId = $this->resolveByName(
            $this->resource->getTableName('etechflow_vehicle_make'),
            'make_id',
            'name',
            (string) $makeSlug
        );
        if ($makeId === null) {
            return null;
        }

        // Model slug → ID (only if provided), scoped to the resolved Make.
        $modelId = null;
        if ($modelSlug !== null) {
            $modelId = $this->resolveByName(
                $this->resource->getTableName('etechflow_vehicle_model'),
                'model_id',
                'name',
                (string) $modelSlug,
                ['make_id' => $makeId]
            );
            if ($modelId === null) {
                return null;
            }
        }

        // Year segment: integer only.
        $year = null;
        if ($yearSeg !== null) {
            if (!ctype_digit((string) $yearSeg)) {
                return null;
            }
            $year = (int) $yearSeg;
        }

        // Resolve the part slug against the `parts_required` multiselect options
        // (the id-based contract FindResults filters on). Consistent with
        // make/model: a present-but-unresolvable part slug is a miss → bail so
        // another router can try (404), rather than silently dropping it.
        $partId = null;
        if ($partSlug !== null && $partSlug !== '') {
            $partId = $this->resolvePartOptionId((string) $partSlug);
            if ($partId === null) {
                return null;
            }
        }

        // Forward using the SAME param names the rest of the module reads —
        // make_id/model_id/year/part_id, NOT make/model/part. These drifted apart
        // after v1.1.0 (dropdown form + FindResults were renamed to *_id, the
        // router was not), so SEO URLs resolved but never actually filtered.
        $params = ['make_id' => $makeId];
        if ($modelId !== null) { $params['model_id'] = $modelId; }
        if ($year    !== null) { $params['year']     = $year; }
        if ($partId  !== null) { $params['part_id']  = $partId; }

        /** @var Http $request */
        $request->setModuleName('vehiclecompat');
        $request->setControllerName('find');
        $request->setActionName('index');
        // NB: Find\Index skips its ugly→pretty 301 by detecting that the request
        // path already starts with the SEO prefix (arrivedViaSeoPath), so no
        // marker param is needed here — keeps the request params clean.
        foreach ($params as $k => $v) {
            $request->setParam($k, $v);
        }

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }

    /**
     * Case-insensitive, slug-tolerant lookup. "3-series" matches "3 Series",
     * "land-rover" matches "Land Rover", etc.
     *
     * @param array<string,mixed> $extraWhere
     */
    private function resolveByName(
        string $table,
        string $idColumn,
        string $nameColumn,
        string $slug,
        array $extraWhere = []
    ): ?int {
        $conn = $this->resource->getConnection();
        $select = $conn->select()
            ->from($table, [$idColumn])
            ->where("LOWER(REPLACE($nameColumn, ' ', '-')) = ?", strtolower($slug))
            ->limit(1);
        foreach ($extraWhere as $col => $val) {
            $select->where("$col = ?", $val);
        }
        $row = $conn->fetchOne($select);
        return $row !== false ? (int) $row : null;
    }

    /**
     * Resolve a part slug ("brake-pads") to its `parts_required` option id — the
     * value FindResults filters on via finset. Slug-tolerant + case-insensitive,
     * mirroring resolveByName(). Null = no such part option.
     */
    private function resolvePartOptionId(string $slug): ?int
    {
        $conn = $this->resource->getConnection();
        $attrId = (int) $conn->fetchOne(
            "SELECT attribute_id FROM " . $this->resource->getTableName('eav_attribute')
            . " WHERE entity_type_id = 4 AND attribute_code = 'parts_required'"
        );
        if ($attrId === 0) {
            return null;
        }
        $select = $conn->select()
            ->from(['o' => $this->resource->getTableName('eav_attribute_option')], ['o.option_id'])
            ->join(
                ['v' => $this->resource->getTableName('eav_attribute_option_value')],
                'v.option_id = o.option_id',
                []
            )
            ->where('o.attribute_id = ?', $attrId)
            ->where('v.store_id = ?', 0)
            ->where("LOWER(REPLACE(v.value, ' ', '-')) = ?", strtolower($slug))
            ->limit(1);
        $optionId = $conn->fetchOne($select);
        return $optionId !== false ? (int) $optionId : null;
    }
}
