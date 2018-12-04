<?php

/**
 * A Magento 2 module named Vesource/Kvk
 * Copyright (C) 2018 Vesource
 * 
 */

namespace Vesource\Kvk\Api;

interface KvkManagementInterface
{

    /**
     * Set a kvk on the cart     
     * @param  string $company
     * @return string
     */
    public function getKvkInformation(string $str,string $mode);
}
