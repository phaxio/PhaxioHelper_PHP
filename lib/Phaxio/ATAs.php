<?php

namespace Phaxio;

class ATAs extends AbstractResources
{
    protected $collection_class = 'ATACollection';

    public function init($id) {
        return ATA::init($this->phaxio, $id);
    }  
}
