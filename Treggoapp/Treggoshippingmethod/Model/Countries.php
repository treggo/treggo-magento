<?php
/**
 * Created by PhpStorm.
 * User: matiasdameno
 * Date: 26/06/2020
 * Time: 01:05
 */

namespace Treggoapp\Treggoshippingmethod\Model;

use \Magento\Framework\Data\OptionSourceInterface;

class Countries implements OptionSourceInterface
{
    /**
     * get allowed countries
     * @return array
     */
    public function toOptionArray() {
        $options = [];

        $options[] = ['label' => 'Argentina', 'value' => 'AR'];
        $options[] = ['label' => 'Mexico', 'value' => 'MX'];
        $options[] = ['label' => 'Uruguay', 'value' => 'UY'];

        return $options;
    }
}