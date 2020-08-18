<?php
/**
 * Created by PhpStorm.
 * User: matiasdameno
 * Date: 17/08/2020
 * Time: 23:09
 */

namespace Treggoapp\Treggoshippingmethod\Model;

use \Magento\Framework\Data\OptionSourceInterface;

class Labels implements OptionSourceInterface
{
    /**
     * get allowed labels
     * @return array
     */
    public function toOptionArray() {
        $options = [];

        $options[] = ['label' => 'A4', 'value' => 'a4'];
        $options[] = ['label' => 'Zebra', 'value' => 'zebra'];

        return $options;
    }
}