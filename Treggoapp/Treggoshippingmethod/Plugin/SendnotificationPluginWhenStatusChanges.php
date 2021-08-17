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
use Magento\Sales\Model\ResourceModel\Order;
use Treggoapp\Treggoshippingmethod\Helper\SendNotificationHelper;

/**
 * Class OrderManagement
 */
class SendnotificationPluginWhenStatusChanges
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
     * @param Order $subject
     * @param $result
     * @param $object
     * @return OrderInterface
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSave(Order $subject, $result, $object)
    {
        /* Sending request to "notifications" endpoint only if shipping method is TREGGO only if status has changed */
        if (
            $object->getShippingMethod() === 'treggoshippingmethod_treggoshippingmethod' &&
            $object->getOrigData('status') !== $object->getData('status')
        ) {
            $this->_helper->send($object);
        }

        return $object;
    }
}
