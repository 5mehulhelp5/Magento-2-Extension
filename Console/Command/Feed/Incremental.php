<?php

/**
 * Copyright (c) 2020 Unbxd Inc.
 */

/**
 * Init development:
 * @author andy
 * @email andyworkbase@gmail.com
 * @team MageCloud
 */

namespace Unbxd\ProductFeed\Console\Command\Feed;

use Unbxd\ProductFeed\Console\Command\Feed\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Unbxd\ProductFeed\Model\CronManager;
use Unbxd\ProductFeed\Model\Feed\Config as FeedConfig;
use Magento\Store\Model\Store;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Incremental
 * @package Unbxd\ProductFeed\Console\Command\Feed
 */
class Incremental extends AbstractCommand
{
    /**
     * Product ID argument key
     */
    const PRODUCTS_ID_ARGUMENT_KEY = 'products_id';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('unbxd:product-feed:incremental')
            ->setDescription('Incremental catalog product(s) synchronization with Unbxd service.')
            ->addArgument(
                self::PRODUCTS_ID_ARGUMENT_KEY,
                InputArgument::IS_ARRAY,
                'Product IDs for synchronization'
            )
            ->addOption(
                self::STORE_INPUT_OPTION_KEY,
                's',
                InputOption::VALUE_REQUIRED,
                'Use the specific Store View',
                Store::DEFAULT_STORE_ID
            );

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initAreaCode($output);

        $stores = [$this->getDefaultStoreId()];
        $storeId = $input->getOption(self::STORE_INPUT_OPTION_KEY);
        if ($storeId) {
            // in case if store code was passed instead of store id
            if (!is_numeric($storeId)) {
                $storeId = $this->getStoreIdByCode($storeId, $stores);
            }
            $stores = [$storeId];
        }

        // check authorization credentials
        if (!$this->feedHelper->isAuthorizationCredentialsSetup($storeId)) {
            $output->writeln("<error>Please check authorization credentials to perform this operation.</error>");
            return 401;
        }

        // check if related cron process doesn't occur to this process to prevent duplicate execution
        $jobs = $this->getCronManager()->getRunningSchedules(CronManager::FEED_JOB_CODE_UPLOAD);
        if ($jobs->getSize()) {
            $message = 'At the moment, the cron job is already executing this process. ' . "\n" . 'To prevent duplicate process, which will increase the load on the server, please try it later.';
            $output->writeln("<error>{$message}</error>");
            return 429;
        }

        // check if product ids was setup
        $productIds = $input->getArgument(self::PRODUCTS_ID_ARGUMENT_KEY);
        if (!count($productIds)) {
            $output->writeln("<error>Product ID(s) are required. Please provide at least one product ID to perform this operation.</error>");
            return 0;
        }

        // pre process actions
        $this->preProcessActions($output);

        $errors = [];
        $start = microtime(true);
        if (!empty($stores)) {
            foreach ($stores as $storeId) {
                $storeName = $this->getStoreNameById($storeId);
                $output->writeln("<info>Performing operations for store with ID {$storeId} ({$storeName}):</info>");
                /** @var \Magento\Store\Model\Store $store */
                try {
                    $output->writeln("<info>Rebuild index...</info>");
                    $index = $this->reindexAction->rebuildProductStoreIndex($storeId, $productIds);
                    $deleteIndex = [];
                    // try to detect deleted product(s)
                    if (!empty($productIds)) {
                        foreach ($productIds as $id) {
                            if (!array_key_exists($id, $index)) {
                                $deleteIndex[$id]['action'] = FeedConfig::OPERATION_TYPE_DELETE;
                                $output->writeln("Following product will be deleted from index - " . $id);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $output->writeln("<error>Indexing error: {$e->getMessage()}</error>");
                    $errors[$storeId] = $e->getMessage();
                    break;
                }

                if (empty($index) && empty($deleteIndex)) {
                    $output->writeln("<error>Index data is empty. Possible reason: product(s) with status 'Disabled' were performed.</error>");
                    return 0;
                }

                try {
                    $output->writeln("<info>Execute feed...</info>");
                    if (!empty($index)) {
                        $this->getFeedManager()->execute($index, FeedConfig::FEED_TYPE_INCREMENTAL, $storeId);
                    }
                    if (!empty($deleteIndex)) {
                        $this->getNewFeedManager()->execute($deleteIndex, FeedConfig::FEED_TYPE_INCREMENTAL, $storeId);
                    }
                } catch (\Exception $e) {
                    $output->writeln("<error>Feed execution error: {$e->getMessage()}</error>");
                    $errors[$storeId] = $e->getMessage();
                    break;
                }
            }
        }

        // post process actions
        $this->postProcessActions($output);

        $this->buildResponse($output, $stores, $errors);

        $end = microtime(true);
        $workingTime = round($end - $start, 2);
        $output->writeln("<info>Working time: {$workingTime}</info>");

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param $stores
     * @param $errors
     * @return $this
     */
    private function buildResponse($output, $stores, $errors)
    {
        $errorMessage = strip_tags(FeedConfig::FEED_MESSAGE_BY_RESPONSE_TYPE_ERROR);
        if (!empty($errors)) {
            $affectedIds = implode(',', array_keys($errors));
            $errorMessages = implode(',', array_values($errors));
            $errorMessage = sprintf($errorMessage, $affectedIds . '. ' . $errorMessages);
            $output->writeln("<error>{$errorMessage}</error>");
        } else if ($this->feedHelper->isLastSynchronizationSuccess()) {
            $output->writeln("<info>" . FeedConfig::FEED_MESSAGE_BY_RESPONSE_TYPE_COMPLETE . "</info>");
        } else if ($this->feedHelper->isLastSynchronizationProcessing()) {
            $output->writeln("<info>" . strip_tags(FeedConfig::FEED_MESSAGE_BY_RESPONSE_TYPE_INDEXING) . "</info>");
        }

        return $this;
    }

    /**
     * @param OutputInterface $output
     * @return $this
     */
    protected function preProcessActions($output)
    {
        return $this;
    }

    /**
     * @param OutputInterface $output
     * @return $this
     */
    protected function postProcessActions($output)
    {
        $this->flushCache();

        return $this;
    }
}
