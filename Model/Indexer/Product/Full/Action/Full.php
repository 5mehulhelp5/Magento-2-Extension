<?php
/**
 * Copyright (c) 2019 Unbxd Inc.
 */

/**
 * Init development:
 * @author andy
 * @email andyworkbase@gmail.com
 * @team MageCloud
 */
namespace Unbxd\ProductFeed\Model\Indexer\Product\Full\Action;

use Unbxd\ProductFeed\Model\ResourceModel\Indexer\Product\Full\Action\Full as ResourceModel;
use Unbxd\ProductFeed\Model\Indexer\Product\Full\DataSourceProvider;
use Unbxd\ProductFeed\Logger\LoggerInterface;
use Unbxd\ProductFeed\Helper\Data as HelperData;
use Unbxd\ProductFeed\Model\Feed\Config as FeedConfig;

/**
 * Unbxd product feed full indexer.
 *
 * Class Full
 * @package Unbxd\ProductFeed\Model\Indexer\Product\Full\Action
 */
class Full
{
    /**
     * @var ResourceModel
     */
    private $resourceModel;

    /**
     * @var DataSourceProvider
     */
    private $dataSourceProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HelperData
     */
    private $helperData;

    /**
     * @var integer
     */
    private $batchRowsCount;

    /**
     * Full constructor.
     * @param ResourceModel $resourceModel
     * @param DataSourceProvider $dataSourceProvider
     * @param LoggerInterface $logger
     * @param HelperData $helperData
     * @param $batchRowsCount
     */
    public function __construct(
        ResourceModel $resourceModel,
        DataSourceProvider $dataSourceProvider,
        LoggerInterface $logger,
        HelperData $helperData,
        $batchRowsCount
    ) {
        $this->resourceModel = $resourceModel;
        $this->dataSourceProvider = $dataSourceProvider;
        $this->logger = $logger;
        $this->helperData = $helperData;
        $this->batchRowsCount = $batchRowsCount;
    }

    /**
     * Load a bulk of product data.
     *
     * @param $storeId
     * @param array $productIds
     * @param int $fromId
     * @param bool $useFilters
     * @param null $limit
     * @return mixed
     * @throws \Exception
     */
    private function getProducts($storeId, $productIds = [], $fromId = 0, $useFilters = false, $limit = null)
    {
        return $this->resourceModel->getProducts($storeId, $productIds, $fromId, $useFilters, $limit);
    }

    /**
     * Get data for a list of product in a store id.
     * If the product list ids is null, all products data will be loaded.
     *
     * @param $storeId
     * @param array $productIds
     * @return array
     * @throws \Exception
     */
    private function initProductStoreIndex($storeId, $productIds = [])
    {
        if (!empty($productIds)) {
            // ensure to reindex also the child product ids, if parent was passed.
            $relationsByParent = $this->resourceModel->getRelationsByParent($productIds);
            if (!empty($relationsByParent)) {
                $productIds = array_unique(array_merge($productIds, $relationsByParent));
            }
        }
		
		$productId = 0;
        do {
            $products = $this->getProducts($storeId, $productIds, $productId);
            foreach ($products as $productData) {
                $productId = (int) $productData['entity_id'];
                // check if product related to parent product, if so - mark it (use for filtering index data in feed process)
                $parentId = $this->resourceModel->getRelatedParentProduct($productId);
                if ($parentId && ($parentId != $productId)) {
                    $productData['parent_id'] = (int) $parentId;
                };
                $productData['has_options'] = (bool) $productData['has_options'];
                $productData['required_options'] = (bool) $productData['required_options'];
                $productData['created_at'] = (string) $this->helperData->formatDateTime($productData['created_at']);
                $productData['updated_at'] = (string) $this->helperData->formatDateTime($productData['updated_at']);
                yield $productId => $productData;
            }
        } while (!empty($products));
    }

    /**
     * @param $data
     * @param $size
     * @return \Generator
     */
    private function getBatchItems($data, $size)
    {
        $i = 0;
        $batch = [];
        foreach ($data as $key => $value) {
            $batch[$key] = $value;
            if (++$i == $size) {
                yield $batch;
                $i = 0;
                $batch = [];
            }
        }
		
        if (count($batch) > 0) {
            yield $batch;
        }
    }

    /**
     * Append product index data on the basis of which feed operation will be performed
     *
     * @param $storeId
     * @param $initIndexData
     * @return array|mixed
     */
    private function appendIndexData($storeId, $initIndexData)
    {
        $index = [];
        $batchSize = $this->batchRowsCount;
        foreach ($this->getBatchItems($initIndexData, $batchSize) as $batchIndex) {
			if (!empty($batchIndex)) {
				foreach ($this->dataSourceProvider->getList() as $dataSource) {
					/** Unbxd\ProductFeed\Model\Indexer\Product\Full\DataSourceProviderInterface $dataSource */
					$batchIndex = $dataSource->appendData($storeId, $batchIndex);
				}
			}
			
			if (!empty($batchIndex)) {
				$index += $batchIndex;
			}
        }
		
        return $index;
    }

    /**
     * Reindex all products data and return reindex result
     *
     * @param $storeId
     * @param array $productIds
     * @return array|mixed
     * @throws \Exception
     */
    public function rebuildProductStoreIndex($storeId, $productIds = [])
    {
        $initIndexData = $this->initProductStoreIndex($storeId, $productIds);
        $fullIndex = [];
        if (!empty($initIndexData)) {
			$fullIndex = $this->appendIndexData($storeId, $initIndexData);
        }

        // try to detect deleted product(s)
        if (!empty($productIds)) {
            foreach ($productIds as $id) {
                if (!$this->resourceModel->getProductSkuById($id)) {
                    $fullIndex[$id]['action'] = FeedConfig::OPERATION_TYPE_DELETE;
                }
            }
        }

        return $fullIndex;
    }
}