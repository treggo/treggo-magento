<?php

namespace Treggo\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\Event\ObserverInterface; 

/**
 * Custom shipping model
 */
class TreggoShipping extends AbstractCarrier implements CarrierInterface, ObserverInterface
{
    /**
     * @var string
     */
    protected $_code = 'treggoshipping';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private $rateMethodFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
    }

    /**
     * Custom Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        if ($this->getConfigFlag('active_z1')) {
            $method = $this->rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle("Treggo");
            $method->setMethod("z1");
            $method->setMethodTitle($this->getConfigData('name_z1'));
            $shippingCost = (float)$this->getConfigData('shipping_cost_z1');
            $method->setPrice($shippingCost);
            $method->setCost($shippingCost);
            $result->append($method);
        }
        if ($this->getConfigFlag('active_z2')) {
            $method = $this->rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle("Treggo");
            $method->setMethod("z2");
            $method->setMethodTitle($this->getConfigData('name_z2'));
            $shippingCost = (float)$this->getConfigData('shipping_cost_z2');
            $method->setPrice($shippingCost);
            $method->setCost($shippingCost);
            $result->append($method);
        }
        if ($this->getConfigFlag('active_z3')) {
            $method = $this->rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle("Treggo");
            $method->setMethod("z3");
            $method->setMethodTitle($this->getConfigData('name_z3'));
            $shippingCost = (float)$this->getConfigData('shipping_cost_z3');
            $method->setPrice($shippingCost);
            $method->setCost($shippingCost);
            $result->append($method);
        }
        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    public function execute(\Magento\Framework\Event\Observer $observer) { 
        $order = $observer->getEvent()->getOrder();
        $OrderShipping=$order->getShippingMethod();
        $email = $this->getConfigData('email');
        $country = $this->getConfigData('country');
        // Only if its a Treggo Shipping or All is selected
        if($this->getConfigFlag('all') 
        || $OrderShipping == "treggoshipping_z1"
        || $OrderShipping == "treggoshipping_z2"
        || $OrderShipping == "treggoshipping_z3"
        ){
            // Only if the ocnfiguration has the minium data
            if($email != null && $country != null){
                $shipment = $order->getShippingAddress();
                $store = $order->getStore();
                $secret = $this->getConfigData('secret');
                $items = [];
                foreach ($order->getAllItems() as $item) {
                    array_push($items,$item->getData());
                }
                $payload = json_encode(array(
                    "order" => (Array)$order->getData(),
                    "shipment" => (Array)$shipment->getData(),
                    "store" => (Array)$store->getData(),
                    "items" => $items,
                    "email" => $email,
                    "secret" => $secret,
                ));
                $url = "https://".$country.".treggo.co/1/integrations/magento";
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => array("Content-Type: application/json"),
                ));
                $response = curl_exec($curl);
                curl_close($curl);
            }
        }
    }
}
