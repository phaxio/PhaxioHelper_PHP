<?php

namespace Phaxio;

class ATA extends AbstractResource
{
    public static function init($phaxio, $id) {
        return new self($phaxio, array('id' => $id));
    }

    public function retrieve() {
        if (!isset($this->id)) throw new Exception("Must set ID before getting fax");

        $result = $this->phaxio->doRequest("GET", 'atas/' . urlencode($this->id));
        $this->exchangeArray($result->getData());

        return $this;
    }    
}
