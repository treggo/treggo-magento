<?php
/**
 * Created by PhpStorm.
 * User: matiasdameno
 * Date: 26/06/2020
 * Time: 00:38
 */

namespace Treggoapp\Treggoshippingmethod\Model\Carrier;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Shipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'treggoshippingmethod';

    /**
     * @var ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $_rateMethodFactory;

    protected $_logger;

    protected $_storeManager;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * Shipping constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface  $scopeConfig,
        ErrorFactory          $rateErrorFactory,
        LoggerInterface       $logger,
        ResultFactory         $rateResultFactory,
        MethodFactory         $rateMethodFactory,
        StoreManagerInterface $storeManager,
        Session               $checkoutSession,
        array                 $data = []
    )
    {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_checkoutSession = $checkoutSession;


        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * get allowed methods
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * @return float|null
     * @throws NoSuchEntityException
     */
    private function getShippingPrice(string $city = null, string $postcode = null)
    {
        $shippingPrice = null;

        /* Logging POST parameters coming from the front-end */
        $this->_logger->info('CITY: ' . $city);
        $this->_logger->info('POSTCODE: ' . $postcode);

        /* Building $data array in order to use for sending TREGGO middleware rates request */
        if (!empty($city) || !empty($postcode)) {
            $data = [
                'email' => $this->_scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE),
                'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
                'cp' => !empty($postcode) ? $postcode : '',
                'locality' => !empty($city) ? $city : ''
            ];

            /* Logging DATA REQUEST in var/log/shipping.log */
            $this->_logger->info('DATA REQUEST:');
            $this->_logger->info(print_r($data, true));

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
                if (isset($result->total_price) && $result->total_price !== null) {
                    $shippingPrice = $this->getFinalPriceWithHandlingFee($result->total_price);

                    /* Setting cookie in order to recover the last price in the final request */
//                    $cookieName = 'treggo_shipping_module_last_price';
//                    $cookieValue = $shippingPrice;
//                    setcookie($cookieName, $cookieValue, time() + 3600);
                    $this->_checkoutSession->setTreggoShippingModuleLastPrice($shippingPrice);
                } elseif (isset($result->message) && $result->message === 'El usuario no tiene coberturas seteadas') {
//                    setcookie('treggo_shipping_module_last_price', null, time() + 3600);
                    $this->_checkoutSession->setTreggoShippingModuleLastPrice(null);

                    return null;
                }
            } catch (\Exception $e) {
                $this->_logger->info(print_r($e->getMessage(), true));
            }
        } else {
            $treggoCookieprice = $this->_checkoutSession->getTreggoShippingModuleLastPrice();
            if (isset($treggoCookieprice)) {
                $shippingPrice = $this->getFinalPriceWithHandlingFee($treggoCookieprice);
            } else {
                return null;
            }
        }

        return $shippingPrice;
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     * @throws NoSuchEntityException
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var Result $result */
        $result = $this->_rateResultFactory->create();

        /** @var Method $method */
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        $city = $request->getDestCity();
        $postcode = $request->getDestPostcode();

        $amount = $this->getShippingPrice($city, $postcode);

        if ($amount === null) {
            return false;
        }

        $multiplierValue = (float)$this->_scopeConfig->getValue('carriers/treggoshippingmethod/multiplier', ScopeInterface::SCOPE_STORE);

        if (!empty($multiplierValue)) {
            $amount = $amount * $multiplierValue;
        }

        if($request->getFreeShipping()){
            $amount = 0;
        }

        $method->setPrice($amount);
        $method->setCost($amount);

        $result->append($method);

        return $result;
    }
}
