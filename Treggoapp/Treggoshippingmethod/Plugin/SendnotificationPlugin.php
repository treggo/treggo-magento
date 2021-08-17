<?php
/**
 * Created by PhpStorm.
 * User: matiasdameno
 * Date: 28/06/2020
 * Time: 03:08
 */

namespace Treggoapp\Treggoshippingmethod\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Treggoapp\Treggoshippingmethod\Helper\SendNotificationHelper;

/**
 * Class OrderManagement
 */
class SendnotificationPlugin
{
    /**
     * @var SendNotificationHelper
     */
    protected $_helper;

    /**
     * @param SendNotificationHelper $helper
     */
    public function __construct(SendNotificationHelper $helper)
    {
        $this->_helper = $helper;
    }

    /**
     * @param OrderManagementInterface $subject
     * @param OrderInterface $result
     * @return OrderInterface
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterPlace(OrderManagementInterface $subject, OrderInterface $result)
    {
        /* Sending request to "notifications" endpoint only if shipping method is TREGGO */
        if ($result->getShippingMethod() === 'treggoshippingmethod_treggoshippingmethod') {
            $this->_helper->send($result);
        }

        return $result;
    }
}
