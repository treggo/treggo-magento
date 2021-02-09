<?php

namespace Treggoapp\Treggoshippingmethod\Controller\Adminhtml\Labels;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Printzebrainbulk extends \Magento\Backend\App\Action
{
    protected $resultPageFactory;

    protected $_orderModel;

    protected $_logger;

    protected $_storeManager;

    protected $_scopeConfig;

    protected $_messageManager;

    public function __construct(Context $context,PageFactory $resultPageFactory, \Magento\Sales\Model\Order $orderModel,
                                PsrLoggerInterface $logger, \Magento\Store\Model\StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig,
                                \Magento\Framework\Message\ManagerInterface $messageManager, \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->_orderModel = $orderModel;
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_messageManager = $messageManager;
        $this->_orderCollectionFactory = $orderCollectionFactory;
    }

    public function execute() {
        $params = $this->getRequest()->getParams();
        $selectedOrdersIds = isset($params['selected']) ? $params['selected'] : null;
        $excludedOrdersIds = isset($params['excluded']) ? $params['excluded'] : null;
        $ordersShippingAddresses = array();

        if(isset($selectedOrdersIds) && count($selectedOrdersIds) <= 50) {
            foreach ($selectedOrdersIds as $orderId) {
                $order = $this->_orderModel->loadByAttribute('entity_id', $orderId);
                $ordersShippingAddresses[] = $order->getShippingAddress()->getData();
            }
        } elseif(isset($selectedOrdersIds) && count($selectedOrdersIds) > 50) {
            return $this->_cancelProcess();
        } elseif(isset($excludedOrdersIds) && $excludedOrdersIds != false) {
            $orders = $this->_orderCollectionFactory->create();
            $filteredOrders = $orders->addFieldToFilter('entity_id', array('nin' => $excludedOrdersIds));

            if(count($filteredOrders) <= 50) {
                foreach ($filteredOrders as $order) {
                    $ordersShippingAddresses[] = $order->getShippingAddress()->getData();
                }
            } else {
                return $this->_cancelProcess();
            }
        } else {
            $orders = $this->_orderCollectionFactory->create();

            if(count($orders) <= 50) {
                foreach ($orders as $order) {
                    $ordersShippingAddresses[] = $order->getShippingAddress()->getData();
                }
            } else {
                return $this->_cancelProcess();
            }
        }

        $storeEmail = $this->_scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE);
        $data = [
            'email' => $storeEmail,
            'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
            'type' => 'zebra',
            'orders' => $ordersShippingAddresses
        ];

        $this->_logger->info('STARTING A4 LABEL PRINTING IN BULK...');
        $this->_logger->info('ORDER INCREMENT IDs:');
        $this->_logger->info(print_r($selectedOrdersIds, true));

        /* Logging DATA REQUEST in var/log/treggoshippingmethod/info.log */
        $this->_logger->info('DATA REQUEST FOR MASS TAG:');
        $this->_logger->info(print_r($data,true));

        try {
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
            header('Content-Disposition: attachment; filename="treggo_etiquetas.pdf"');
            header('Content-Length: '.strlen($tagResult));

            echo $tagResult;
        } catch(Exception $e) {
            $this->_logger->info(print_r($e->getMessage(), true));
            $this->_messageManager->addErrorMessage('Se produjo un error en el servidor. Por favor, pongasé en contacto con el administrador de la tienda.');
            $resultRedirect = $this->resultRedirectFactory->create();
            $url = $this->_redirect->getRefererUrl();
            $resultRedirect->setUrl($url);

            return $resultRedirect;
        }
    }

    protected function _cancelProcess() {
        $this->_messageManager->addWarningMessage('Se pueden imprimir un máximo de 50 etiquetas en simultáneo.');
        $resultRedirect = $this->resultRedirectFactory->create();
        $url = $this->_redirect->getRefererUrl();
        $resultRedirect->setUrl($url);

        return $resultRedirect;
    }
}