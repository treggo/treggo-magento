<?php

namespace Treggoapp\Treggoshippingmethod\Helper;

use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\CatalogInventory\Model\StockFactory;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Inventory\Model\ResourceModel\Stock;

class SendNotificationHelper extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context                      $context,
        StoreManagerInterface        $storeManager
    )
    {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
    }

    /**
     * @param Order|OrderInterface $order
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function send($order)
    {
        /* Logging shipping address and shipping method information for further debugging purposes */
        $this->_logger->info('SHIPPING ADDRESS DATA:');
        $this->_logger->info(print_r($order->getShippingAddress()->getData(), true));

        $storeEmail = $this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE);

        $data = [
            'email' => $storeEmail,
            'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
            'order' => $order->getShippingAddress()->getData()
        ];

        $data['order']['status'] = $order->getStatus();
        $data['order']['store'] = str_replace("<br />\n", ' - ', $order->getStore()->getFormattedAddress());

        /* Logging DATA REQUEST in var/log/treggoshippingmethod/info.log */
        $this->_logger->info('DATA REQUEST FOR NOTIFICATION:');
        $this->_logger->info(print_r($data, true));

        try {
            /* Initiating CURL library instance */
            $curl = curl_init();

            /* Setting CURL options... */
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($curl, CURLOPT_URL, 'https://api.treggo.co/1/integrations/magento/notifications');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded',
                'cache-control: no-cache'
            ));

            /* Executing CURL request and parsing it from JSON to a PHP array */
            $notificationsResult = curl_exec($curl);
            $notificationsResult = json_decode($notificationsResult);

            /* Closing CURL connection */
            curl_close($curl);

            /* Logging CURL RESPONSE in var/log/treggoshippingmethod/info.log */
            $this->_logger->info('CURL RESPONSE FOR ORDER NOTIFICATION:');
            $this->_logger->info(print_r($notificationsResult, true));
        } catch (\Exception $e) {
            $this->_logger->info(print_r($e->getMessage(), true));
        }
    }
}
