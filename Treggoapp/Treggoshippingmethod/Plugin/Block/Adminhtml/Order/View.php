<?php

namespace Treggoapp\Treggoshippingmethod\Plugin\Block\Adminhtml\Order;

/**
 * Class View
 */
class View
{
    protected $_urlBuilder;
    protected $_scopeConfig;

    public function __construct(\Magento\Backend\Model\UrlInterface $urlBuilder, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig) {
        $this->_urlBuilder= $urlBuilder;
        $this->_scopeConfig = $scopeConfig;
    }

    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\View $view) {
        $order = $view->getOrder();

        if($order->getShippingMethod() === 'treggoshippingmethod_treggoshippingmethod') {
            $labelOptions = explode(',',$this->_scopeConfig->getValue('carriers/treggoshippingmethod/allowedlabeltypes'));

            if(array_search('a4',$labelOptions) !== false) {
                $view->addButton(
                    'treggoapp-add-print-a4-label-button',
                    [
                        'label' => 'Etiqueta A4',
                        'onclick' => 'setLocation("' . $this->_urlBuilder->getUrl('treggoprintlabel/labels/printa4',array('orderIncrementId' => $order->getIncrementId())) . '")'
                    ]
                );
            }

            if(array_search('zebra',$labelOptions) !== false) {
                $view->addButton(
                    'treggoapp-add-print-zebra-label-button',
                    [
                        'label' => 'Etiqueta Zebra',
                        'onclick' => 'setLocation("' . $this->_urlBuilder->getUrl('treggoprintlabel/labels/printzebra',array('orderIncrementId' => $order->getIncrementId())) . '")'
                    ]
                );
            }
        }
    }
}