<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Garage;

use ETechFlow\ProductFitmentFinder\Model\Config as VcConfig;
use ETechFlow\ProductFitmentFinder\Setup\Patch\Data\AddCustomerGarageAttribute;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * AJAX endpoint backing the logged-in-customer garage sync.
 *
 * POST /vehiclecompat/garage/sync   { action: "save"|"clear", vehicles: [...] }
 * GET  /vehiclecompat/garage/sync   (load saved vehicles for the logged-in customer)
 *
 * Behaviour:
 *   - Guest:        returns 401 (the JS falls back to localStorage-only)
 *   - Logged-in:    save/load/clear against the `etechflow_vc_garage`
 *                   customer attribute. Stored as JSON-encoded text.
 *
 * Capped at the merchant's garage_max_entries config so a runaway script
 * can't bloat the customer attribute with thousands of entries.
 */
class Sync implements HttpGetActionInterface, HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SerializerInterface $serializer,
        private readonly VcConfig $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Module-disabled or garage-disabled: short-circuit. Don't leak info.
        if (!$this->config->isEnabled() || !$this->config->isSavedGarageEnabled()) {
            return $result->setHttpResponseCode(404)->setData(['error' => 'Not available']);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData(['error' => 'Not logged in']);
        }

        $method = strtoupper((string) $this->request->getMethod());

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);

            if ($method === 'GET') {
                $vehicles = $this->loadVehicles($customer);
                return $result->setData(['vehicles' => $vehicles]);
            }

            // POST handler — save or clear.
            $payload = $this->parsePayload();
            $action = (string) ($payload['action'] ?? 'save');

            if ($action === 'clear') {
                $customer->setCustomAttribute(AddCustomerGarageAttribute::ATTRIBUTE_CODE, '');
                $this->customerRepository->save($customer);
                return $result->setData(['cleared' => true]);
            }

            // 'save' — merge incoming vehicles + persist.
            $incoming = is_array($payload['vehicles'] ?? null) ? $payload['vehicles'] : [];
            $existing = $this->loadVehicles($customer);

            // De-dupe by label; incoming entries take priority (most recent).
            $merged = [];
            foreach ($incoming as $v) {
                if (is_array($v) && !empty($v['label'])) {
                    $merged[(string) $v['label']] = $this->sanitiseVehicle($v);
                }
            }
            foreach ($existing as $v) {
                if (is_array($v) && !empty($v['label']) && !isset($merged[(string) $v['label']])) {
                    $merged[(string) $v['label']] = $this->sanitiseVehicle($v);
                }
            }

            $max = max(1, min(10, $this->config->getGarageMaxEntries()));
            $finalList = array_slice(array_values($merged), 0, $max);

            $customer->setCustomAttribute(
                AddCustomerGarageAttribute::ATTRIBUTE_CODE,
                $this->serializer->serialize($finalList)
            );
            $this->customerRepository->save($customer);

            return $result->setData(['vehicles' => $finalList, 'saved' => true]);

        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_ProductFitmentFinder: garage sync failed.', [
                'exception' => $e->getMessage(),
                'method'    => $method,
            ]);
            return $result->setHttpResponseCode(500)->setData(['error' => 'Sync failed']);
        }
    }

    /**
     * Read the customer's saved garage (deserialised) — empty array on
     * empty attribute or malformed JSON.
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadVehicles(\Magento\Customer\Api\Data\CustomerInterface $customer): array
    {
        $attr = $customer->getCustomAttribute(AddCustomerGarageAttribute::ATTRIBUTE_CODE);
        $raw = $attr ? (string) $attr->getValue() : '';
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = $this->serializer->unserialize($raw);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Cap each vehicle to known-shape fields to avoid attribute bloat. */
    private function sanitiseVehicle(array $v): array
    {
        return [
            'makeId'     => isset($v['makeId']) ? (int) $v['makeId'] : null,
            'makeLabel'  => isset($v['makeLabel']) ? mb_substr((string) $v['makeLabel'], 0, 64) : '',
            'modelId'    => isset($v['modelId']) ? (int) $v['modelId'] : null,
            'modelLabel' => isset($v['modelLabel']) ? mb_substr((string) $v['modelLabel'], 0, 64) : '',
            'year'       => isset($v['year']) ? (int) $v['year'] : null,
            'label'      => mb_substr((string) ($v['label'] ?? ''), 0, 128),
        ];
    }

    private function parsePayload(): array
    {
        $body = (string) $this->request->getContent();
        if ($body !== '') {
            try {
                $decoded = $this->serializer->unserialize($body);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable $e) { /* fall through to form-encoded */ }
        }
        // Fall back to form-encoded params.
        return (array) $this->request->getParams();
    }
}
