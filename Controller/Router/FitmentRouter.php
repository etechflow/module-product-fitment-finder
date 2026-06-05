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

        // Part slug is freeform — forward as-is for the Find controller
        // to interpret against the product `parts_required` attribute.
        $params = ['make' => $makeId];
        if ($modelId !== null) { $params['model'] = $modelId; }
        if ($year    !== null) { $params['year']  = $year; }
        if ($partSlug !== null && $partSlug !== '') {
            $params['part'] = (string) $partSlug;
        }

        /** @var Http $request */
        $request->setModuleName('vehiclecompat');
        $request->setControllerName('find');
        $request->setActionName('index');
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
}
