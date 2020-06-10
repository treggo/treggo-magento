<?php
namespace Treggo\Shipping\Model;

use \Magento\Framework\Option\ArrayInterface; 

class Countries implements ArrayInterface{
    public function toOptionArray(){
        $options = [];
        $options[] = ['label' => 'Argentina', 'value' => 'api'];
        $options[] = ['label' => 'Mexico', 'value' => 'mx.api'];
        $options[] = ['label' => 'Uruguay', 'value' => 'uy.api'];
        return $options;
    }
}