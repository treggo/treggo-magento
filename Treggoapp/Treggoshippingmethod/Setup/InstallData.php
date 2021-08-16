<?php
/**
 * Created by PhpStorm.
 * User: matiasdameno
 * Date: 28/06/2020
 * Time: 21:48
 */

namespace Treggoapp\Treggoshippingmethod\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class InstallData implements InstallDataInterface
{
    protected $_logger;

    protected $_storeManager;

    protected $_scopeConfig;

    public function __construct(PsrLoggerInterface                         $logger,
                                \Magento\Store\Model\StoreManagerInterface $storeManager,
                                ScopeConfigInterface                       $scopeConfig
    )
    {
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /* Using scopeConfig interface and storeManager object in order to get store information */
        $generalContactStoreEmail = $this->_scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE);
        $generalContactStoreName = $this->_scopeConfig->getValue('trans_email/ident_general/name', ScopeInterface::SCOPE_STORE);
        $storeInformationTelephone = $this->_scopeConfig->getValue('general/store_information/phone', ScopeInterface::SCOPE_STORE);
        $storeForSendingSignUpRequest = [
            'nombre' => $this->_scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE),
            'dominio' => $this->_storeManager->getStore()->getBaseUrl(),
            'id' => $this->_storeManager->getStore()->getId()
        ];

        /* Preparing data array for sending the sign up request*/
        $data = [
            'email' => $generalContactStoreEmail,
            'nombre' => $generalContactStoreName,
            'telefono' => $storeInformationTelephone,
            'store' => $storeForSendingSignUpRequest
        ];

        /* Logging DATA REQUEST in var/log/treggoshippingmethod/info.log */
        $this->_logger->info('DATA REQUEST FOR SIGN UP:');
        $this->_logger->info(print_r($data, true));

        try {
            /* Initiating CURL library instance */
            $curl = curl_init();

            /* Setting CURL options... */
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($curl, CURLOPT_URL, 'https://api.treggo.co/1/integrations/magento/signup');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded',
                'cache-control: no-cache'
            ));

            /* Executing CURL request and parsing it from JSON to a PHP array */
            $signupResult = curl_exec($curl);
            $signupResult = json_decode($signupResult);

            /* Closing CURL connection */
            curl_close($curl);

            /* Logging CURL RESPONSE in var/log/treggoshippingmethod/info.log */
            $this->_logger->info('CURL RESPONSE FOR SIGN UP:');
            $this->_logger->info(print_r($signupResult, true));
        } catch (Exception $e) {
            $this->_logger->info(print_r($e->getMessage(), true));
        }
    }
}
