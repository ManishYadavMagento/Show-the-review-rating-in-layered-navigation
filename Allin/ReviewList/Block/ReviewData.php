<?php

namespace Allin\ReviewList\Block;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;

class ReviewData extends Template
{
    protected $productCollectionFactory;
    protected $categoryFactory;
    protected $registry;
    protected $reviewCollectionFactory;
    protected $imageHelper;
    protected $_reviewFactory;
    protected $_storeManager;

    public function __construct(
        Template\Context $context,
        ProductCollectionFactory $productCollectionFactory,
        CategoryFactory $categoryFactory,
        Registry $registry,
        ReviewCollectionFactory $reviewCollectionFactory,
        ImageHelper $imageHelper,
        ReviewFactory $reviewFactory,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryFactory = $categoryFactory;
        $this->registry = $registry;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->imageHelper = $imageHelper;
        $this->_reviewFactory = $reviewFactory;
        $this->_storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function getProductCollection()
    {
        $category = $this->getCurrentCategory();
        if ($category) {
            $productIdsWithReviews = $this->getProductIdsWithReviews();
            if (!empty($productIdsWithReviews)) {
                $collection = $this->productCollectionFactory->create();
                $collection->addAttributeToSelect('*')
                       ->addCategoryFilter($category)
                       ->addFieldToFilter('entity_id', ['in' => $productIdsWithReviews])
                       ->addFieldToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
                       ->addFieldToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                       ->setOrder('entity_id', 'ASC');
                return $collection;
            }
        }
        return [];
    }

    protected function getCurrentCategory()
    {
        return $this->registry->registry('current_category');
    }

    public function getReviewsForProduct($productId)
    {
        $reviewCollection = $this->reviewCollectionFactory->create();
        $reviewCollection->addEntityFilter('product', $productId)
                         ->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)
                         ->setDateOrder()->load()->addRateVotes();

        return $reviewCollection;
    }

    protected function getProductIdsWithReviews()
    {
        $reviewCollection = $this->reviewCollectionFactory->create();
        $reviewCollection->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)
                         ->setDateOrder()
                         ->distinct(true);

        $productIds = [];
        foreach ($reviewCollection as $review) {
            $productIds[] = $review->getEntityPkValue();
        }

        return array_unique($productIds);
    }

    public function getProductImageUrl($product)
    {
        return $this->imageHelper->init($product, 'product_page_image_small')->getUrl();
    }

    public function getRatingSummary($product)
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $this->_reviewFactory->create()->getEntitySummary($product, $storeId);
        $ratingSummary = $product->getRatingSummary()->getRatingSummary();

        return $ratingSummary;
    }
}
