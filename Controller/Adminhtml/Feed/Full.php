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
namespace Unbxd\ProductFeed\Controller\Adminhtml\Feed;

use Unbxd\ProductFeed\Controller\Adminhtml\ActionIndex;
use Magento\Framework\Controller\ResultFactory;
use Unbxd\ProductFeed\Model\IndexingQueue;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Full
 * @package Unbxd\ProductFeed\Controller\Adminhtml\Feed
 */
class Full extends ActionIndex
{
    /**
     * @return array|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $responseContent = [];

        $isValid = $this->_isValidPostRequest();
        if (!$isValid) {
            $responseContent = [
                'errors' => true,
                'message' => __('Invalid request.')
            ];
            $resultJson->setData($responseContent);
            return $resultJson;
        }

        $data = $this->getRequest()->getPostValue();
        $this->prepareData($data);

        /** @var \Unbxd\ProductFeed\Model\IndexingQueue $indexingQueue */
        $queue = $this->indexingQueueFactory->create();
        $queue->setData($data);

        try {
            $this->indexingQueueRepository->save($queue);
            $this->messageManager->addSuccessMessage(
                __('Sync operation was added to queue. Please make sure the related cron job is configured to perform this operation.')
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving this operation.'));
        }

        $resultJson->setData($responseContent);
        return $resultJson;
    }

    /**
     * Prepare post data for adding to indexing queue
     *
     * @param array $data
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function prepareData(array &$data)
    {
        if (!isset($data[IndexingQueue::QUEUE_ID])) {
            $data[IndexingQueue::QUEUE_ID] = null;
        }
        if (!isset($data[IndexingQueue::STORE_ID])) {
            $data[IndexingQueue::STORE_ID] = $this->getStore()->getId();
        }
        if (!isset($data[IndexingQueue::STATUS])) {
            $data[IndexingQueue::STATUS] = IndexingQueue::STATUS_PENDING;
        }
        if (!isset($data[IndexingQueue::EXECUTION_TIME])) {
            $data[IndexingQueue::EXECUTION_TIME] = 0;
        }
        if (!isset($data[IndexingQueue::DATA_FOR_PROCESSING])) {
            $data[IndexingQueue::DATA_FOR_PROCESSING] = __(IndexingQueue::REINDEX_FULL_LABEL);
        }
        if (!isset($data[IndexingQueue::NUMBER_OF_ENTITIES])) {
            $data[IndexingQueue::NUMBER_OF_ENTITIES] = count($this->productHelper->getAllProductsIds());
        }
        if (!isset($data[IndexingQueue::ACTION_TYPE])) {
            $data[IndexingQueue::ACTION_TYPE] = IndexingQueue::TYPE_REINDEX_FULL;
        }
    }
}