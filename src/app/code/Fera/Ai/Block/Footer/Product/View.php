<?php
/**
 * @author: Sviatoslav Lashkiv
 * @email: ss.lashkiv@gmail.com
 * @team: MageCloud
 */

namespace Fera\Ai\Block\Footer\Product;

use Magento\Catalog\Block\Product\Context;
use Magento\Framework\UrlInterface;
use Magento\Framework\Url\EncoderInterface as UrlEncoder;
use Magento\Framework\Json\EncoderInterface as JsonEncoder;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Fera\Ai\Helper\Data as FeraHelper;

/**
 * Class View
 * @package Fera\Ai\Block\Footer\Product
 */
class View extends \Magento\Catalog\Block\Product\View
{
    /**
     * @var StockStateInterface
     */
    protected $stockState;

    /**
     * @var FeraHelper
     */
    protected $helper;

    /**
     * View constructor.
     * @param Context $context
     * @param UrlEncoder $urlEncoder
     * @param JsonEncoder $jsonEncoder
     * @param StringUtils $string
     * @param ProductHelper $productHelper
     * @param ConfigInterface $productTypeConfig
     * @param FormatInterface $localeFormat
     * @param CustomerSession $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param StockStateInterface $stockState
     * @param FeraHelper $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        UrlEncoder $urlEncoder,
        JsonEncoder $jsonEncoder,
        StringUtils $string,
        ProductHelper $productHelper,
        ConfigInterface $productTypeConfig,
        FormatInterface $localeFormat,
        CustomerSession $customerSession,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        StockStateInterface $stockState,
        FeraHelper $helper,
        array $data = []
    )
    {
        $this->stockState = $stockState;
        $this->helper = $helper;
        parent::__construct($context, $urlEncoder, $jsonEncoder, $string, $productHelper, $productTypeConfig, $localeFormat, $customerSession, $productRepository, $priceCurrency, $data);
    }

    /**
     * @return bool|mixed
     */
    public function isEnabled()
    {
        return $this->helper->isEnabled();
    }

    /**
     * Get product, JSONify it and return it.
     * @return string
     */
    public function getProductJson()
    {
        $p = $this->getProduct();
        $thumb = $this->helper->getProductThumbnailUrl($p);

        $productData = [
            "id" =>               $p->getId(), // String
            "name" =>             $p->getName(), // String
            "price" =>            $p->getFinalPrice(), // Float
            "status" =>           $p->getStatus() == 1 ? 'published' : 'draft', // (Optional) String
            "created_at" =>       $this->helper->formatDate($p->getCreatedAt()), // (Optional) String (ISO 8601 format DateTime)
            "modified_at" =>      $this->helper->formatDate($p->getUpdatedAt()), // (Optional) String (ISO 8601 format DateTime)
            "stock" =>            $this->stockState->getStockQty($p->getId(), $p->getStore()->getWebsiteId()), // (Optional) Integer, If null assumed to be infinite.
            "in_stock" =>         $p->isInStock(), // (Optional) Boolean
            "url" =>              $p->getProductUrl(), // String
            "thumbnail_url" =>    $thumb, // String
            "needs_shipping" =>   $p->getTypeId() != 'virtual', // (Optional) Boolean
            "hidden" =>           $p->getVisibility() == '1', // (Optional) Boolean
            'tags' =>             [], // M2 not included tags by default
            "variants" =>         [], // (Optional) Array<Variant>: Variants that are applicable to this product.
            "platform_data" => [ // (Optional) Hash/Object of attributes to store about the product specific to the integration platform (can be used in future filters)
                "sku" => $p->getSku(),
                "type" => $p->getTypeId(),
                "regular_price" => $p->getPrice()
            ]
        ];

        if ($p->getTypeId() == 'configurable') {
            $cfgAttr = $p->getTypeInstance()->getConfigurableAttributesAsArray($p);

            foreach ($p->getTypeInstance()->getUsedProducts($p) as $subProduct) {

                $variant = [
                    "id" =>               $subProduct->getId(),
                    "name" =>             $subProduct->getName(), // String
                    "status" =>           $subProduct->getStatus() == 1 ? 'published' : 'draft', // (Optional) String
                    "created_at" =>       $this->helper->formatDate($subProduct->getCreatedAt()), // (Optional) String (ISO 8601 format DateTime)
                    "modified_at" =>      $this->helper->formatDate($subProduct->getUpdatedAt()), // (Optional) String (ISO 8601 format DateTime)
                    "stock" =>            $this->stockState->getStockQty($subProduct->getId(), $subProduct->getStore()->getWebsiteId()), // (Optional) Integer, If null assumed to be infinite.
                    "in_stock" =>         $subProduct->isInStock(), // (Optional) Boolean
                    "price" =>            $subProduct->getPrice(), // Float
                    "platform_data" => [ // (Optional) Hash/Object of attributes to store about the product specific to the integration platform (can be used in future filters)
                        "sku" => $subProduct->getSku()
                    ]
                ];

                $variantImage = $this->helper->getProductThumbnailUrl($subProduct);
                if ($variantImage != $thumb && stripos($variantImage, '/placeholder') === false){
                    $variant['thumbnail_url'] = $variantImage;
                }

                $variantAttrVals = [];
                foreach ($cfgAttr as $attr) {
                    $attrValIndex = $subProduct->getData($attr['attribute_code']);
                    foreach ($attr['values'] as $attrVal) {
                        if ($attrVal['value_index'] == $attrValIndex) {
                            $variantAttrVals[] = $attrVal['label'];
                        }
                    }
                }
                $variant['name'] = implode(' / ', $variantAttrVals);

                $productData['variants'][] = $variant;
            }
        }

        return $this->helper->jsonEncode($productData);
    }

    /**
     * @return int
     */
    public function getProductId()
    {
        return $this->getProduct()->getId();
    }

}
