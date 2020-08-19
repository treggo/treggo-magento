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

    public function __construct(Context $context,PageFactory $resultPageFactory, \Magento\Sales\Model\Order $orderModel,
                                PsrLoggerInterface $logger, \Magento\Store\Model\StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->_orderModel = $orderModel;
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
    }

    public function execute() {
        $params = $this->getRequest()->getParams();
        $orderIds = isset($params['selected']) ? $params['selected'] : null;
        $orders = array();

        if(isset($orderIds) && count($orderIds) >= 1) {
            $this->_logger->info('STARTING ZEBRA LABEL PRINTING IN BULK...');
            $this->_logger->info('ORDER INCREMENT IDs:');
            $this->_logger->info(print_r($orderIds,true));

            $storeEmail = $this->_scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE);

            foreach($orderIds as $orderId) {
                $order = $this->_orderModel->loadByAttribute('entity_id', $orderId);
                $ordersShippingAddresses[] = $order->getShippingAddress()->getData();
            }

            $data = [
                'email' => $storeEmail,
                'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
                'type' => 'zebra',
                'orders' => $ordersShippingAddresses
            ];

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
                header('Content-Disposition: attachment; filename="new.pdf"');
                header('Content-Length: '.strlen($tagResult));
                echo $tagResult;
            } catch(Exception $e) {
                $this->_logger->info(print_r($e->getMessage(), true));
            }
        } else {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('admin/sales/order');

            return $resultRedirect;
        }
    }
}