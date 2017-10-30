<?php

namespace Phaxio;

class Fax extends \ArrayObject
{
    private $phaxio;

    public function __construct($phaxio, $id = null)
    {
        $this->setFlags(\ArrayObject::ARRAY_AS_PROPS);
        $this->phaxio = $phaxio;
        $this->id = $id;
    }

    public static function create($phaxio, $params) {
        $fax = new self($phaxio);
        return $fax->_create($params);
    }

    public static function retrieve($phaxio, $id) {
        $fax = new self($phaxio, $id);
        return $fax->get();
    }

    public function _create($params) {
        if ($this->id) throw new PhaxioException("Fax #$id already created");

        $result = $this->phaxio->doRequest('POST', 'faxes', $params);
        $this->id = $result->getData()['id'];

        return $this;
    }

    public function get() {
        if (!$this->id) throw new PhaxioException("Must set ID before getting fax");

        $result = $this->phaxio->doRequest("GET", 'faxes/' . urlencode($this->id));
        $this->exchangeArray($result->getData());

        return $this;
    }

    public function cancel() {
        $result = $this->phaxio->doRequest("POST", 'faxes/' . urlencode($this->id) . "/cancel");

        return $result;
    }

    public function resend($params = array()) {
        $result = $this->phaxio->doRequest("POST", 'faxes/' . urlencode($this->id) . "/resend", $params);

        return new self($this->phaxio, $result->getData()['id']);
    }
}
