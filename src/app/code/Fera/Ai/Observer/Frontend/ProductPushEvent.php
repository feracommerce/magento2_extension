<?php
namespace Fera\Ai\Observer\Frontend;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\HTTP\Client\Curl as Curl;
use Fera\Ai\Helper\Data as FeraHelper;

class ProductPushEvent implements ObserverInterface
{
    protected $_curl;
    protected $helper;
    protected $stockState;

    /**
     * Product view constructor.
     * @param FeraHelper $helper
     * @param StockStateInterface $stockState
     * @param array $data
     */
    public function __construct(
        FeraHelper $helper,
        StockStateInterface $stockState,
        Curl $curl,

    )
    {
        $this->stockState = $stockState;
        $this->helper = $helper;
        $this->_curl = $curl;
    }

    /**
     * Send post request to push orders
     */
    public function pushProducts($data) {
        $url = $this->helper->getApiUrl(). "v3/private/products.json";
        $this->_curl->addHeader("Content-Type", "application/json");
        $this->_curl->addHeader("SECRET-KEY", $this->helper->getSecretKey());
        $this->_curl->post($url, $this->helper->jsonEncode($data));

        $response = $this->_curl->getBody();
    }

    /**
    * Use curl to push products to our server.
    *
    * @param Observer $observer
    */
   public function execute(Observer $observer)
   {
       if (!$this->helper->isEnabled()) { return; }

       $p = $observer->getProduct();
       $thumb = $this->helper->getProductThumbnailUrl($p);
       $productData = [
            "id" =>               $p->getId(), // String
            "external_id" =>      $p->getId(), // String
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

      $this->pushProducts($productData);
   }
}
