<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Options;

use ETechFlow\ProductFitmentFinder\Block\PartFinderData;
use ETechFlow\ProductFitmentFinder\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\LayoutInterface;

/**
 * /vehiclecompat/options/index — server-side filtered dropdown options for the
 * Find-Your-Parts widget. Replaces the previous client-side tree filter.
 *
 * Query params (all optional):
 *   field    : "make" | "model" | "year" | "part"     (required)
 *   make_id  : int                                    (current Make selection)
 *   model_id : int                                    (current Model selection)
 *   year     : int                                    (current Year selection)
 *   part_id  : int                                    (current Part selection)
 *
 * Response: {"options":[{"id":<int>,"name":"<string>"}, ...]}
 *
 * The aggregated tree is still computed once by PartFinderData and cached
 * server-side (block cache, invalidated on product save). Each request walks
 * the cached tree, applies all selections except the one being requested,
 * and returns the surviving distinct values for that field.
 */
class Index implements HttpGetActionInterface
{
    private RawFactory $rawFactory;
    private LayoutInterface $layout;
    private RequestInterface $request;
    private Config $config;

    public function __construct(
        RawFactory $rawFactory,
        LayoutInterface $layout,
        RequestInterface $request,
        Config $config
    ) {
        $this->rawFactory = $rawFactory;
        $this->layout = $layout;
        $this->request = $request;
        $this->config = $config;
    }

    public function execute(): ResponseInterface|Raw
    {
        // Licence gate: no dropdown data for an unlicensed store.
        if (!$this->config->isEnabled()) {
            $blocked = $this->rawFactory->create();
            $blocked->setHttpResponseCode(404);
            return $blocked;
        }

        $field = (string) $this->request->getParam('field', '');
        $selMake  = (int) $this->request->getParam('make_id', 0);
        $selModel = (int) $this->request->getParam('model_id', 0);
        $selYear  = (int) $this->request->getParam('year', 0);
        $selPart  = (int) $this->request->getParam('part_id', 0);

        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $result->setHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0', true);

        if (!in_array($field, ['make', 'model', 'year', 'part'], true)) {
            $result->setHttpResponseCode(400);
            $result->setContents(json_encode(['error' => 'invalid field']));
            return $result;
        }

        /** @var PartFinderData $block */
        $block = $this->layout->createBlock(PartFinderData::class);
        $tree  = $block->getTree();

        // Build a part-id -> label map for the "part" field response.
        $partLabels = [];
        foreach ($tree['parts'] ?? [] as $p) {
            $partLabels[(int) $p['id']] = (string) $p['name'];
        }

        // Pass 1: walk the tree, skipping rows that violate any active filter
        // EXCEPT the field being requested (so e.g. when asked for makes we
        // still apply selModel/selYear/selPart, but ignore selMake).
        $makes = [];   // id => name
        $models = [];  // id => name
        $years = [];   // set of ints
        $parts = [];   // set of ints

        $ignoreMake  = ($field === 'make');
        $ignoreModel = ($field === 'model');
        $ignoreYear  = ($field === 'year');
        $ignorePart  = ($field === 'part');

        foreach ($tree['makes'] ?? [] as $mk) {
            if (!$ignoreMake && $selMake && (int) $mk['id'] !== $selMake) {
                continue;
            }
            foreach ($mk['models'] ?? [] as $mod) {
                if (!$ignoreModel && $selModel && (int) $mod['id'] !== $selModel) {
                    continue;
                }
                $modYears = $mod['years'] ?? [];
                $modParts = $mod['parts'] ?? [];
                if (!$ignoreYear && $selYear && !in_array($selYear, $modYears, true)) {
                    continue;
                }
                if (!$ignorePart && $selPart && !in_array($selPart, $modParts, true)) {
                    continue;
                }
                $makes[(int) $mk['id']]   = (string) $mk['name'];
                $models[(int) $mod['id']] = (string) $mod['name'];
                foreach ($modYears as $y) {
                    $years[(int) $y] = true;
                }
                foreach ($modParts as $p) {
                    $parts[(int) $p] = true;
                }
            }
        }

        $options = [];
        switch ($field) {
            case 'make':
                foreach ($makes as $id => $name) {
                    $options[] = ['id' => $id, 'name' => $name];
                }
                usort($options, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                break;

            case 'model':
                foreach ($models as $id => $name) {
                    $options[] = ['id' => $id, 'name' => $name];
                }
                usort($options, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                break;

            case 'year':
                $yearList = array_keys($years);
                rsort($yearList);
                foreach ($yearList as $y) {
                    $options[] = ['id' => $y, 'name' => (string) $y];
                }
                break;

            case 'part':
                foreach (array_keys($parts) as $pid) {
                    $options[] = ['id' => $pid, 'name' => $partLabels[$pid] ?? ('Part #' . $pid)];
                }
                usort($options, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                break;
        }

        $result->setContents(json_encode(['options' => $options]));
        return $result;
    }
}
