<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model\Attribute\Backend;

use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;

/**
 * Stores vehicle_compat_data as JSON. Accepts both flat
 * ([{make_id, model_id, years},...]) and grouped
 * ([{make_id, models:[{model_id, years},...]}]) input shapes.
 * Always stores flat for predictability — frontend/migration both group at render time.
 */
class JsonBackend extends AbstractBackend
{
    public function beforeSave($object)
    {
        $code = $this->getAttribute()->getAttributeCode();
        $value = $object->getData($code);

        if (is_array($value)) {
            $rows = $this->normalizeFlat($value);
            $object->setData($code, $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE) : null);
        } elseif (is_string($value) && trim($value) === '') {
            $object->setData($code, null);
        }
        return parent::beforeSave($object);
    }

    private function normalizeFlat(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;

            /* Grouped shape: expand into flat rows */
            if (isset($row['models']) && is_array($row['models'])) {
                $makeId   = (int)($row['make_id'] ?? 0);
                $makeName = (string)($row['make_name'] ?? '');
                if ($makeId <= 0) continue;
                foreach ($row['models'] as $m) {
                    if (!is_array($m)) continue;
                    $modelId = (int)($m['model_id'] ?? 0);
                    if ($modelId <= 0) continue;
                    $out[] = [
                        'make_id'    => $makeId,
                        'make_name'  => $makeName,
                        'model_id'   => $modelId,
                        'model_name' => (string)($m['model_name'] ?? ''),
                        'years'      => $this->cleanYears($m['years'] ?? []),
                    ];
                }
                continue;
            }

            /* Flat shape */
            $makeId  = (int)($row['make_id'] ?? 0);
            $modelId = (int)($row['model_id'] ?? 0);
            if ($makeId <= 0 || $modelId <= 0) continue;
            $out[] = [
                'make_id'    => $makeId,
                'make_name'  => (string)($row['make_name'] ?? ''),
                'model_id'   => $modelId,
                'model_name' => (string)($row['model_name'] ?? ''),
                'years'      => $this->cleanYears($row['years'] ?? []),
            ];
        }
        return $out;
    }

    private function cleanYears($years): array
    {
        $ys = array_values(array_filter(array_map('intval', (array)$years)));
        sort($ys);
        return $ys;
    }
}
