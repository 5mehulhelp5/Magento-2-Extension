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
namespace Unbxd\ProductFeed\Block\Adminhtml\ViewDetails;

use Unbxd\ProductFeed\Block\Adminhtml\ViewDetails;
use Unbxd\ProductFeed\Model\IndexingQueue as IndexingQueueModel;

/**
 * Class IndexingQueue
 * @package Unbxd\ProductFeed\Block\Adminhtml\ViewDetails
 */
class IndexingQueue extends ViewDetails
{
    /**
     * Retrieve current queue item instance
     *
     * @return IndexingQueueModel
     */
    public function getItem()
    {
        return $this->registry->registry('indexing_queue_item');
    }
}