<?php
/**
 * @author andy
 * @email andyworkbase@gmail.com
 * @team MageCloud
 */
namespace Unbxd\ProductFeed\Ui\Component\Listing\Column\FeedView;

use Magento\Ui\Component\Listing\Columns\Column;
use Unbxd\ProductFeed\Model\FeedView;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;

/**
 * Class Status
 * @package Unbxd\ProductFeed\Ui\Component\Listing\Column\FeedView
 */
class Status extends Column
{
    /**
     * @var FeedView
     */
    protected $feedView;

    /**
     * @var FilterManager
     */
    protected $filterManager;

    /**
     * Status constructor.
     * @param FeedView $feedView
     * @param FilterManager $filterManager
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        FeedView $feedView,
        FilterManager $filterManager,
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
        $this->feedView = $feedView;
        $this->filterManager = $filterManager;
    }

    /**
     * Prepare data source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item['status'] = $this->decorateStatus($item['status']);
            }
        }

        return $dataSource;
    }

    /**
     * @param int $status
     * @return string
     */
    private function decorateStatus($status)
    {
        $availableStatuses = $this->feedView->getAvailableStatuses();
        $decoratorClassPath = 'undefined';
        $title = 'Undefined';
        if (array_key_exists($status, $availableStatuses)) {
            $title = $availableStatuses[$status];
            if ($status == FeedView::STATUS_RUNNING) {
                $decoratorClassPath = 'minor';
            } elseif ($status == FeedView::STATUS_COMPLETE) {
                $decoratorClassPath = 'notice';
            } elseif ($status == FeedView::STATUS_ERROR) {
                $decoratorClassPath = 'critical';
            }
        }

        $cell = '<span class="grid-severity-' . $decoratorClassPath .'"><span>' . __($title) . '</span></span>';

        return $cell;
    }
}
