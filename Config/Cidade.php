<?php

namespace Kaisari\Loggi\Model\Config;

/**
 * Class Formato
 * @package Bleez\Correios\Model\Config
 */
class Cidade implements \Magento\Framework\Option\ArrayInterface {
    /**
     * @return array
     */
    public function toOptionArray() {
        return array(
            array('value' => 1, 'label' => 'São Paulo'),
            array('value' => 2, 'label' => 'Rio de Janeiro'),
            array('value' => 3, 'label' => 'Belo Horizonte'),
            array('value' => 4, 'label' => 'Curitiba'),
        );
    }
}
