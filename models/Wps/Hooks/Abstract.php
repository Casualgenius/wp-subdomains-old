<?php

abstract class Wps_Hooks_Abstract
{
    /**
     * @var Wps_Plugin
     */
    protected $_plugin;

    public function __construct (Wps_Plugin $plugin)
    {
        $this->_plugin = $plugin;
    }
    
}