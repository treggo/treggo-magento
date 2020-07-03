<?php
/**
 * Created by PhpStorm.
 * User: matiasdameno
 * Date: 28/06/2020
 * Time: 03:08
 */

namespace Treggo\Treggoshippingmethod\Plugin;

use Magento\Checkout\Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class OrderManagement
 */
class SendnotificationPlugin
{
    protected $_logger;

    protected $_storeManager;

    /**
     * SendnotificationPlugin constructor.
     *
     * @param PsrLoggerInterface $logger
     */
    public function __construct(PsrLoggerInterface $logger,\Magento\Store\Model\StoreManagerInterface $storeManager,ScopeConfigInterface $scopeConfig) {
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * @param OrderManagementInterface $subject
     * @param OrderInterface           $order
     *
     * @return OrderInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterPlace(OrderManagementInterface $subject,OrderInterface $result) {
        /* Sending request to "notifications" endpoint only if shipping method is TREGGO */
        if($result->getShippingMethod() === 'treggoshippingmethod_treggoshippingmethod') {
            /* Logging shipping address and shipping method information for further debugging purposes */
            $this->_logger->info('SHIPPING ADDRESS DATA:');
            $this->_logger->info(print_r($result->getShippingAddress()->getData(),true));

            $storeEmail = $this->_scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE);

            $data = [
                'email' => $storeEmail,
                'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
                'order' => $result->getShippingAddress()->getData()
            ];

            $data['order']['status'] = $result->getStatus();

            /* Logging DATA REQUEST in var/log/treggoshippingmethod/info.log */
            $this->_logger->info('DATA REQUEST FOR NOTIFICATION:');
            $this->_logger->info(print_r($data,true));

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
                $this->_logger->info('CURL RESPONSE FOR NOTIFICATIONS:');
                $this->_logger->info(print_r($notificationsResult, true));
            } catch(Exception $e) {
                $this->_logger->info(print_r($e->getMessage(), true));
            }
        }

        return $result;
    }
}