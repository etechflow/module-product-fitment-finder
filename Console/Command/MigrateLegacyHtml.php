<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Console\Command;

use ETechFlow\ProductFitmentFinder\Model\MakeFactory;
use ETechFlow\ProductFitmentFinder\Model\ModelFactory;
use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make as MakeResource;
use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Model as ModelResource;
use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make\CollectionFactory as MakeCollectionFactory;
use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Model\CollectionFactory as ModelCollectionFactory;
use Magento\Framework\App\State;
use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento etechflow:vehiclecompat:migrate [--dry-run] [--product-id=N]
 *
 * Reads codazon_custom_tab HTML, parses Make/Model/Year rows, writes JSON to vehicle_compat_data.
 */
class MigrateLegacyHtml extends Command
{
    private State $appState;
    private ResourceConnection $resourceConnection;
    private MakeFactory  $makeFactory;
    private ModelFactory $modelFactory;
    private MakeResource $makeResource;
    private ModelResource $modelResource;
    private MakeCollectionFactory  $makeCollectionFactory;
    private ModelCollectionFactory $modelCollectionFactory;

    public function __construct(
        State $appState,
        ResourceConnection $resourceConnection,
        MakeFactory $makeFactory,
        ModelFactory $modelFactory,
        MakeResource $makeResource,
        ModelResource $modelResource,
        MakeCollectionFactory $makeCollectionFactory,
        ModelCollectionFactory $modelCollectionFactory
    ) {
        parent::__construct();
        $this->appState     = $appState;
        $this->resourceConnection = $resourceConnection;
        $this->makeFactory  = $makeFactory;
        $this->modelFactory = $modelFactory;
        $this->makeResource = $makeResource;
        $this->modelResource = $modelResource;
        $this->makeCollectionFactory  = $makeCollectionFactory;
        $this->modelCollectionFactory = $modelCollectionFactory;
    }

    protected function configure(): void
    {
        $this->setName('etechflow:vehiclecompat:migrate')
            ->setDescription('Migrate legacy codazon_custom_tab HTML tables to structured vehicle_compat_data JSON.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and print only — do not write to DB.')
            ->addOption('product-id', null, InputOption::VALUE_OPTIONAL, 'Migrate only this product ID.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Throwable $e) { /* already set */ }

        $dryRun = (bool)$input->getOption('dry-run');
        $singleId = $input->getOption('product-id') ? (int)$input->getOption('product-id') : null;

        $conn = $this->resourceConnection->getConnection();
        $codazonAttrId = $this->getAttributeId($conn, 'codazon_custom_tab');
        $newAttrId     = $this->getAttributeId($conn, 'vehicle_compat_data');
        if (!$codazonAttrId) {
            $output->writeln('<error>Attribute codazon_custom_tab not found.</error>');
            return Command::FAILURE;
        }
        if (!$newAttrId) {
            $output->writeln('<error>Attribute vehicle_compat_data not found. Run setup:upgrade first.</error>');
            return Command::FAILURE;
        }

        $table = $conn->getTableName('catalog_product_entity_text');
        $sql = "SELECT entity_id, value FROM $table WHERE attribute_id = $codazonAttrId AND store_id = 0 AND value LIKE '%<table%'";
        if ($singleId) {
            $sql .= " AND entity_id = $singleId";
        }
        $rows = $conn->fetchAll($sql);

        $output->writeln(sprintf('Found <info>%d</info> products with legacy HTML.', count($rows)));

        $migrated = 0; $skipped = 0; $failed = 0;

        foreach ($rows as $r) {
            $pid = (int)$r['entity_id'];
            $html = (string)$r['value'];

            /* Skip if vehicle_compat_data already populated for this product */
            $existing = $conn->fetchOne("SELECT value FROM $table WHERE attribute_id=$newAttrId AND store_id=0 AND entity_id=$pid");
            if ($existing && is_string($existing) && trim($existing) !== '' && $existing[0] === '[') {
                $skipped++;
                continue;
            }

            try {
                $parsed = $this->parseHtml($html);
                if (!$parsed) {
                    $output->writeln("  <comment>SKIP</comment> product $pid: parse returned no rows");
                    $skipped++;
                    continue;
                }
                $structured = $this->buildStructured($parsed);
                if (!$structured) {
                    $output->writeln("  <comment>SKIP</comment> product $pid: nothing to write after grouping");
                    $skipped++;
                    continue;
                }
                $json = json_encode($structured, JSON_UNESCAPED_UNICODE);
                $output->writeln("  <info>OK</info> product $pid → " . count($structured) . " make(s), " . array_sum(array_map(fn($x) => count($x['models']), $structured)) . " model row(s)");
                if (!$dryRun) {
                    $conn->insertOnDuplicate($table, [
                        'attribute_id' => $newAttrId,
                        'store_id'     => 0,
                        'entity_id'    => $pid,
                        'value'        => $json,
                    ], ['value']);
                }
                $migrated++;
            } catch (\Throwable $e) {
                $output->writeln("  <error>FAIL</error> product $pid: " . $e->getMessage());
                $failed++;
            }
        }

        $output->writeln(sprintf(
            "\n<info>Summary:</info> migrated=%d  skipped=%d  failed=%d%s",
            $migrated, $skipped, $failed, $dryRun ? '  <comment>(DRY-RUN — nothing written)</comment>' : ''
        ));
        return Command::SUCCESS;
    }

    private function getAttributeId(\Magento\Framework\DB\Adapter\AdapterInterface $conn, string $code): int
    {
        return (int)$conn->fetchOne("SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 4", [$code]);
    }

    /**
     * Parse <table> HTML into rows: [['make' => 'BMW', 'model' => '3 Series', 'year' => '2010-2015'], ...]
     * Header row (containing the strings "Make"/"Model"/"Year") is skipped.
     */
    private function parseHtml(string $html): array
    {
        /* Normalize escaped \n */
        $html = str_replace(['\\n', '\\r'], ' ', $html);

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        $rows = [];
        $trs = $dom->getElementsByTagName('tr');
        foreach ($trs as $tr) {
            $cells = [];
            foreach ($tr->getElementsByTagName('td') as $td) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $td->textContent ?? ''));
            }
            if (count($cells) < 3) continue;
            /* Header row detection */
            $lower = array_map('strtolower', $cells);
            if (in_array('make', $lower, true) && (in_array('model', $lower, true) || in_array('year', $lower, true))) {
                continue;
            }
            $rows[] = [
                'make'  => $cells[0],
                'model' => $cells[1],
                'year'  => $cells[2],
            ];
        }
        return $rows;
    }

    /**
     * Convert flat rows → grouped structured shape.
     * Auto-creates missing makes / models.
     */
    private function buildStructured(array $rows): array
    {
        $byMake = [];

        foreach ($rows as $r) {
            $makeName  = trim($r['make']);
            $modelName = trim($r['model']);
            if ($makeName === '' || $modelName === '') continue;

            $makeId = $this->resolveOrCreateMake($makeName);
            if (!$makeId) continue;
            $modelId = $this->resolveOrCreateModel($makeId, $modelName);
            if (!$modelId) continue;

            $years = $this->parseYears($r['year']);

            $byMake[$makeId] ??= [
                'make_id'   => $makeId,
                'make_name' => $makeName,
                'models'    => [],
            ];

            $found = false;
            foreach ($byMake[$makeId]['models'] as &$mm) {
                if ($mm['model_id'] === $modelId) {
                    $mm['years'] = array_values(array_unique(array_merge($mm['years'], $years)));
                    sort($mm['years']);
                    $found = true;
                    break;
                }
            }
            unset($mm);

            if (!$found) {
                $byMake[$makeId]['models'][] = [
                    'model_id'   => $modelId,
                    'model_name' => $modelName,
                    'years'      => $years,
                ];
            }
        }

        return array_values($byMake);
    }

    /**
     * Parse "2001-2004" → [2001..2004]; "2010+" → [2010..currentYear+1];
     * "2008,2010,2012" → [2008,2010,2012]; "2015" → [2015]; anything weird → [].
     */
    private function parseYears(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];

        $now = (int)date('Y') + 1;
        $min = 1990;

        /* Range "YYYY-YYYY" */
        if (preg_match('/^(\d{4})\s*[-–]\s*(\d{4})$/', $text, $m)) {
            $a = (int)$m[1]; $b = (int)$m[2];
            if ($a > $b) [$a, $b] = [$b, $a];
            return range(max($a, $min), min($b, $now));
        }
        /* "YYYY+" or "YYYY onwards" */
        if (preg_match('/^(\d{4})\s*(?:\+|onwards?|on)$/i', $text, $m)) {
            $a = (int)$m[1];
            return range(max($a, $min), $now);
        }
        /* "<YYYY" or "pre YYYY" */
        if (preg_match('/^(?:<|pre|before)\s*(\d{4})$/i', $text, $m)) {
            return range($min, max($min, (int)$m[1] - 1));
        }
        /* Comma list */
        if (preg_match_all('/\b(19|20)\d{2}\b/', $text, $matches)) {
            $years = array_map('intval', $matches[0]);
            $years = array_filter($years, fn($y) => $y >= $min && $y <= $now);
            sort($years);
            return array_values(array_unique($years));
        }
        return [];
    }

    private function resolveOrCreateMake(string $name): int
    {
        $needle = strtolower($name);
        foreach ($this->makeCollectionFactory->create() as $m) {
            if (strtolower((string)$m->getData('name')) === $needle) {
                return (int)$m->getId();
            }
        }
        $model = $this->makeFactory->create();
        $model->setName($name);
        $model->setSortOrder(999);
        $this->makeResource->save($model);
        return (int)$model->getId();
    }

    private function resolveOrCreateModel(int $makeId, string $name): int
    {
        $needle = strtolower($name);
        foreach ($this->modelCollectionFactory->create()->addFieldToFilter('make_id', $makeId) as $m) {
            if (strtolower((string)$m->getData('name')) === $needle) {
                return (int)$m->getId();
            }
        }
        $model = $this->modelFactory->create();
        $model->setMakeId($makeId);
        $model->setName($name);
        $model->setSortOrder(999);
        $this->modelResource->save($model);
        return (int)$model->getId();
    }
}
