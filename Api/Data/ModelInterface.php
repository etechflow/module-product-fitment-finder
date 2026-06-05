<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Api\Data;

interface ModelInterface
{
    public const MODEL_ID   = 'model_id';
    public const MAKE_ID    = 'make_id';
    public const NAME       = 'name';
    public const SORT_ORDER = 'sort_order';

    public function getModelId();
    public function setModelId($id);
    public function getMakeId(): ?int;
    public function setMakeId(int $makeId);
    public function getName(): ?string;
    public function setName(string $name);
    public function getSortOrder(): int;
    public function setSortOrder(int $sortOrder);
}
