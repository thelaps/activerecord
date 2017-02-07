<?php

namespace component;

abstract class CApplicationComponent extends \component\CComponent
{
    public $behaviors=array();
    private $_initialized=false;
    public function init()
    {
        $this->attachBehaviors($this->behaviors);
        $this->_initialized=true;
    }
    public function getIsInitialized()
    {
        return $this->_initialized;
    }
}