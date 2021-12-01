<?php

namespace Treggoapp\Treggoshippingmethod\Controller\Adminhtml\Labels;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\ShipOrderInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Printzebra extends \Magento\Backend\App\Action
{
    protected $resultPageFactory;
    protected $_orderModel;
    protected $_logger;
    protected $_storeManager;
    protected $_scopeConfig;
    /**
     * @var ShipOrderInterface
     */
    private $_shiOrder;
    public function __construct(Context $context,PageFactory $resultPageFactory, \Magento\Sales\Model\Order $orderModel,
                                PsrLoggerInterface $logger, \Magento\Store\Model\StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig,ShipOrderInterface $shipOrder) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->_orderModel = $orderModel;
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_shiOrder = $shipOrder;
    }

    public function execute() {
        $params = $this->getRequest()->getParams();
        $orderIncrementId = isset($params['orderIncrementId']) ? $params['orderIncrementId'] : null;

        if($orderIncrementId && $this->existTreggoShipment($orderIncrementId)) {
            $this->_logger->info('STARTING INDIVIDUAL ZEBRA LABEL PRINT...');
            $this->_logger->info('ORDER INCREMENT ID:');
            $this->_logger->info(print_r($orderIncrementId,true));

            $order = $this->_orderModel->loadByIncrementId($orderIncrementId);
            if (!$order->hasShipments()){
                $this->_shiOrder->execute($order->getId());
            }
            $shippingAddressData = $order->getShippingAddress()->getData();
            $shippingAddressData['entity_id'] = $orderIncrementId;

            /* Logging shipping address and shipping method information for further debugging purposes */
            $this->_logger->info('SHIPPING ADDRESS DATA:');
            $this->_logger->info(print_r($shippingAddressData,true));

            $storeEmail = $this->_scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE);

            $data = [
                'email' => $storeEmail,
                'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
                'type' => 'zebra',
                'orders' => array($shippingAddressData)
            ];

            /* Logging DATA REQUEST in var/log/treggoshippingmethod/info.log */
            $this->_logger->info('DATA REQUEST FOR INDIVIDUAL TAG:');
            $this->_logger->info(print_r($data,true));

            try {
                $filename = $orderIncrementId . '_' . date_timestamp_get(date_create());
                /* Initiating CURL library instance */
                $curl = curl_init();

                /* Setting CURL options... */
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($curl, CURLOPT_URL, 'https://api.treggo.co/1/integrations/magento/tags');
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'cache-control: no-cache'
                ));

                /* Executing CURL request... */
                $tagResult = curl_exec($curl);

                /* Closing CURL connection */
                curl_close($curl);

                header('Cache-Control: public');
                header('Content-type: application/pdf');
                header("Content-Disposition: attachment; filename=\"$filename.pdf\"");
                header('Content-Length: '.strlen($tagResult));
                echo $tagResult;
            } catch(Exception $e) {
                $this->_logger->info(print_r($e->getMessage(), true));
            }
        }
        else{
            $this->messageManager->addErrorMessage('Treggo: Error en el recupero de la informacion del envio');
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setRefererOrBaseUrl();
            return $resultRedirect;
        }
    }

    private function existTreggoShipment($orderId){
        $this->_logger->info('CHECKING TREGGO ORDER BY ID...');
        $this->_logger->info('ORDER INCREMENT ID:');
        $this->_logger->info($orderId);
        $storeEmail = $this->_scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE);

        $data = [
            'email' => $storeEmail,
            'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
            'id' => $orderId
        ];

        /* Logging DATA REQUEST in var/log/treggoshippingmethod/info.log */
        $this->_logger->info('DATA REQUEST FOR ORDER CHECK:');
        $this->_logger->info(print_r($data,true));

        /* Initiating CURL library instance */
        $curl = curl_init();

        /* Setting CURL options... */
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_URL, 'https://api.treggo.co/1/integrations/magento/check');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
            'cache-control: no-cache'
        ));

        /* Executing CURL request... */
        $response = curl_exec($curl);

        /* Logging CURL RESPONSE in var/log/treggoshippingmethod/info.log */
        $this->_logger->info('CURL RESPONSE FOR CHECK:');
        $this->_logger->info(print_r($response, true));

        /* Getting HTTP CODE */
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        /* Logging CURL HTTP CODE RESPONSE in var/log/treggoshippingmethod/info.log */
        $this->_logger->info('HTTP CODE:');
        $this->_logger->info(print_r($httpcode, true));

        /* Closing CURL connection */
        curl_close($curl);

        return $httpcode == 200;
    }
}