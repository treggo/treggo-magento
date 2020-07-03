<?php
/**
 * Created by PhpStorm.
 * User: matiasdameno
 * Date: 26/06/2020
 * Time: 00:38
 */

namespace Treggo\Treggoshippingmethod\Model\Carrier;

use Magento\Checkout\Exception;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Store\Model\ScopeInterface;


class Shipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'treggoshippingmethod';

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    protected $_logger;

    protected $_storeManager;

    /**
     * Shipping constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface          $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory  $rateErrorFactory
     * @param \Psr\Log\LoggerInterface                                    $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory                  $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Store\Model\StoreManagerInterface                  $rateResultFactory
     * @param array                                                       $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * get allowed methods
     * @return array
     */
    public function getAllowedMethods() {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * @return float|null
     */
    private function getShippingPrice() {
        /* Getting post parameters from this model class */
        $requestBody = file_get_contents('php://input');
        $params = json_decode($requestBody, true);

        /* Logging POST parameters coming from the front-end */
        $this->_logger->info('PARAMS:');
        $this->_logger->info(print_r($params,true));

        /* Building $data array in order to use for sending TREGGO middleware rates request */
        if(isset($params['address']) || isset($params['addressInformation'])) {
            if (isset($params['address'])) {
                $data = [
                    'email' => $this->_scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE),
                    'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
                    'cp' => $params['address']['postcode'],
                    'locality' => $params['address']['city']
                ];
            } elseif (isset($params['addressInformation'])) {
                $data = [
                    'email' => $this->_scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE),
                    'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
                    'cp' => $params['addressInformation']['shipping_address']['postcode'],
                    'locality' => $params['addressInformation']['shipping_address']['city']
                ];
            }

            /* Logging DATA REQUEST in var/log/shipping.log */
            $this->_logger->info('DATA REQUEST:');
            $this->_logger->info(print_r($data,true));

            try {
                /* Initiating CURL library instance */
                $curl = curl_init();

                /* Setting CURL options... */
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($curl, CURLOPT_URL, 'https://api.treggo.co/1/integrations/magento/rates');
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'cache-control: no-cache'
                ));

                /* Executing CURL request and parsing it from JSON to a PHP array */
                $result = curl_exec($curl);
                $result = json_decode($result);

                /* Closing CURL connection */
                curl_close($curl);

                /* Logging CURL RESPONSE in var/log/shipping.log */
                $this->_logger->info('CURL RESPONSE:');
                $this->_logger->info(print_r($result, true));


                /* If we got a price then we have shipping availability, if not, then we should hide the shipping method */
                if(isset($result->total_price) && $result->total_price !== null) {
                    $shippingPrice = $this->getFinalPriceWithHandlingFee($result->total_price);

                    /* Setting cookie in order to recover the last price in the final request */
                    $cookieName = 'treggo_shipping_module_last_price';
                    $cookieValue = $shippingPrice;
                    setcookie($cookieName, $cookieValue, time()+3600);
                } elseif(isset($result->message) && $result->message === 'El usuario no tiene coberturas seteadas') {
                    setcookie('treggo_shipping_module_last_price', null, time()+3600);

                    return null;
                }
            } catch(Exception $e) {
                $this->_logger->info(print_r($e->getMessage(),true));
            }
        } else {
            if($_COOKIE['treggo_shipping_module_last_price']) {
                $shippingPrice = $this->getFinalPriceWithHandlingFee($_COOKIE['treggo_shipping_module_last_price']);
            } else {
                return null;
            }
        }

        return $shippingPrice;
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request) {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        $amount = $this->getShippingPrice();

        if($amount === null) {
            return false;
        }

        $method->setPrice($amount);
        $method->setCost($amount);

        $result->append($method);

        return $result;
    }
}