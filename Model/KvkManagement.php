<?php
namespace Vesource\Kvk\Model;

use Vesource\Kvk\Helper\Data as HelperData;

class KvkManagement
{

    /**
     * @var \Vesource\Kvk\Helper\Data
     */
    protected $kvkHelper;
   
    public function __construct(
        HelperData $kvkHelper
    ) {
        $this->kvkHelper = $kvkHelper;
    }

    /**
     * @param string $company 
     * @return string
     */
    public function getKvkInformation(string $str,string $mode)
    {        
        $result = $this->kvkHelper->lookupKvk($str,$mode);
        return json_encode($result);
    }
}
