<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Api\Data;

interface MakeInterface
{
    public const MAKE_ID    = 'make_id';
    public const NAME       = 'name';
    public const SORT_ORDER = 'sort_order';

    public function getMakeId();
    public function setMakeId($id);
    public function getName(): ?string;
    public function setName(string $name);
    public function getSortOrder(): int;
    public function setSortOrder(int $sortOrder);
}
