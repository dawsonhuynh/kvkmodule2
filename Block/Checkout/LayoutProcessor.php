<?php
namespace Vesource\Kvk\Block\Checkout;

// class LayoutProcessor extends AbstractBlock implements LayoutProcessorInterface
class LayoutProcessor {

    protected $_scopeConfig;
    protected $_eavConfig;

    public function __construct(\Magento\Framework\View\Element\Template\Context $context,\Magento\Eav\Model\Config $eavConfig, array $data = []) {
        $this->_scopeConfig = $context->getScopeConfig(); //
        $this->_eavConfig = $eavConfig; //
    }

    public function afterProcess($subject, array $result) {       
        if($this->_scopeConfig->getValue('kvknl_api/general/enabled',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            $result = $this->getShippingCompanyField($result);
            $result = $this->getBillingCompanyField($result);
        }
           
        return $result;
    }
    
    public function getCompanyField($scope){
        $config = [
            'component' => 'Vesource_Kvk/js/view/form/searchcompany',
            'config' => [
                "customScope" => $scope,
                "template" => 'ui/form/field',
                "elementTmpl" => 'Vesource_Kvk/form/input'
            ],                
            'provider' => 'checkoutProvider',
            'dataScope' => $scope.'.company',
            'label' => __('Find your company details by entering your company name or kvk number in the field below'),
            'deps'=>[
                'checkoutProvider',                      
            ],
            'streetLines'=>$this->_scopeConfig->getValue('customer/address/street_lines')
        ];
        $sortOrder = '';
        
        if($this->_scopeConfig->getValue('kvknl_api/general/to_top',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            $sortOrder = '1';
            
        }else{
            $attribute = $this->_eavConfig->getAttribute('customer_address', 'company');
            
            if($attribute->getSortOrder()){
                $sortOrder = $attribute->getSortOrder();
            }
        }
        
        if($sortOrder){
            return array_merge($config,['sortOrder'=>$sortOrder]);
        }else{
            return $config;
        }
    }
    
    public function getShippingCompanyField($result)
    {
        if (isset(
            $result['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children']
            ['shipping-address-fieldset']['children']['company']
        )) {
            $result['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children']
            ['shipping-address-fieldset']['children']['company'] = $this->getCompanyField('shippingAddress');
        }

        return $result;
    }
    
    public function getBillingCompanyField($result)
    {
        if (isset(
            $result['components']['checkout']['children']['steps']['children']
            ['billing-step']['children']['payment']['children']['payments-list']
        )) {
            $paymentForms = $result['components']['checkout']['children']['steps']['children']
            ['billing-step']['children']['payment']['children']
            ['payments-list']['children'];
            
            //need to check it more
            foreach ($paymentForms as $paymentMethodForm => $paymentMethodValue) {
                $paymentMethodCode = str_replace('-form', '', $paymentMethodForm);

                if (!isset($result['components']['checkout']['children']['steps']['children']['billing-step']
                    ['children']['payment']['children']['payments-list']['children'][$paymentMethodCode . '-form']['children']['form-fields']['children']['company'])) {
                    continue;
                }

                $result['components']['checkout']['children']['steps']['children']['billing-step']
                ['children']['payment']['children']['payments-list']['children'][$paymentMethodCode . '-form']
                ['children']['form-fields']['children']['company'] = $this->getCompanyField('billingAddress' . $paymentMethodCode);
            }
        }

        return $result;
    }
}
