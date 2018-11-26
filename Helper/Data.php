<?php

/**
 * A Magento 2 module named Vesource/Kvk
 * Copyright (C) 2018 Vesource
 *
 * This file included in Vesource/Kvk
 * 
 */

namespace Vesource\Kvk\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;

class Data extends AbstractHelper
{

    const API_TIMEOUT = 5;
    const API_URL = 'https://api.kvk.nl/api/v2/search/companies';
    const API_KEY = 'l7xx2cca3b2798df458a8a8d49e8e0a4478e';

    const EXCEPTION_NOT_AUTHORIZED = 'KvkNl_Controller_Plugin_HttpBasicAuthentication_NotAuthorizedException';
    const EXCEPTION_PASSWORD_NOT_CORRECT ='KvkNl_Controller_Plugin_HttpBasicAuthentication_PasswordNotCorrectException';

    protected $modules = null;

    protected $enrichType = 0;

    protected $httpResponseRaw = null;
    protected $httpResponseCode = null;
    protected $httpResponseCodeClass = null;
    protected $httpClientError = null;
    protected $debuggingOverride = false;

    protected $productMetadataInterface;

    protected $moduleList;

    protected $developerHelper;

    public function __construct(
        ProductMetadataInterface $productMetadataInterface,
        ModuleListInterface $moduleList,
        \Magento\Developer\Helper\Data $developerHelper,
        Context $context
    ) {
        $this->productMetadataInterface = $productMetadataInterface;
        $this->moduleList = $moduleList;
        $this->developerHelper = $developerHelper;
        parent::__construct($context);
    }


    /**
     * Get the html for initializing validation script.
     *
     * @param bool $getAdminConfig
     *
     * @return array
     */
    public function getJsinit($getAdminConfig = false)//need to edit
    {
        if ($getAdminConfig && !$this->getStoreConfig('kvknl_api/advanced_config/admin_validation_enabled')) {
            return [];
        }

        $settings = [
            //"baseUrl"=> htmlspecialchars($baseUrl),
            "debug" => $this->isDebugging(),
            "translations" => [
                "defaultError" => htmlspecialchars(__('Unknown kvk + housenumber combination.'))
            ]
        ];

        return $settings;
    }

    /**
     * Check if we're currently in debug mode, and if the current user may see dev info.
     *
     * @return bool
     */
    public function isDebugging()
    {
        if ($this->debuggingOverride) {
            return true;
        }

        return (bool)$this->getStoreConfig('kvknl_api/advanced_config/api_debug') &&
            $this->developerHelper->isDevAllowed();
    }

    /**
     * Set the debugging override flag.
     *
     * @param bool $toggle
     */
    protected function setDebuggingOverride($toggle)
    {
        $this->debuggingOverride = $toggle;
    }

    /**
     * Lookup information about a Dutch address by kvk, house number, and house number addition
     *
     * @param string $kvk
     * @param string $houseNumber
     * @param string $houseNumberAddition
     *
     * @return string|array
     */
    public function lookupKvk(string $str,$mode)
    {
        // Check if we are we enabled, configured & capable of handling an API request
        $message = $this->checkApiReady();
        if ($message) {
            return $message;
        }

        $response = array();

        // Some basic user data 'fixing', remove any not-letter, not-number characters
//        $kvk = preg_replace('~[^a-z0-9]~i', '', $kvk);

        // Basic kvk format checking
//        if (!preg_match('~^[1-9][0-9]{3}[a-z]{2}$~i', $kvk)) {
//            $response['message'] = __('Invalid kvk format, use `1234AB` format.');
//            $response['messageTarget'] = 'kvk';
//            return $response;
//        }

        $strSearchEnc = rawurlencode($str);
        $url = "{$this->getServiceUrl()}?".($mode=="BY_COMPANYNAME"?"q=":"kvkNumber=").$strSearchEnc."&apikey={$this->getKey()}";
        
        $jsonData = $this->callApiUrlGet($url);

        if ($this->getStoreConfig('kvknl_api/development_config/api_showcase')) {
            $response['showcaseResponse'] = $jsonData;
        }

        if ($this->isDebugging()) {
            $response['debugInfo'] = $this->getDebugInfo($url, $jsonData);
        }
        
        $response['mode'] = $mode;

        if ($this->httpResponseCode == 200 && is_array($jsonData)) {
            $response = array_merge($response, ['items'=>$jsonData]);
        } else {
            $response = $this->processErrorMessage($jsonData, $response);
        }

        return $response;
//	return $jsonData; 
    }

    protected function processErrorMessage($jsonData, $response)
    {
        if (is_array($jsonData) && isset($jsonData['exceptionId'])) {
            if ($this->httpResponseCode == 400 || $this->httpResponseCode == 404) {
                if (in_array($jsonData['exceptionId'], [
                    'KvkNl_Controller_Address_KvkTooShortException',
                    'KvkNl_Controller_Address_KvkTooLongException',
                    'KvkNl_Controller_Address_NoKvkSpecifiedException',
                    'KvkNl_Controller_Address_InvalidKvkException',
                ])) {
                    $response['message'] = __('Invalid kvk format, use `1234AB` format.');
                    $response['messageTarget'] = 'kvk';
                } elseif (in_array($jsonData['exceptionId'], [
                    'KvkNl_Service_KvkAddress_AddressNotFoundException',
                ])) {
                    $response['message'] = __('Unknown kvk + housenumber combination.');
                    $response['messageTarget'] = 'housenumber';
                } elseif (in_array($jsonData['exceptionId'], [
                    'KvkNl_Controller_Address_InvalidHouseNumberException',
                    'KvkNl_Controller_Address_NoHouseNumberSpecifiedException',
                    'KvkNl_Controller_Address_NegativeHouseNumberException',
                    'KvkNl_Controller_Address_HouseNumberTooLargeException',
                    'KvkNl_Controller_Address_HouseNumberIsNotAnIntegerException',
                ])) {
                    $response['message'] = __('Housenumber format is not valid.');
                    $response['messageTarget'] = 'housenumber';
                } else {
                    $response['message'] = __('Incorrect address.');
                    $response['messageTarget'] = 'housenumber';
                }
            } else {
                if (is_array($jsonData) && isset($jsonData['exceptionId'])) {
                    $response['message'] = __('Validation error, please use manual input.');
                    $response['messageTarget'] = 'housenumber';
                    $response['useManual'] = true;
                }
            }
        } else {
            $response['message'] = __('Validation unavailable, please use manual input.');
            $response['messageTarget'] = 'housenumber';
            $response['useManual'] = true;
        }
        return $response;
    }

    /**
     * Set the enrichType number, or text/class description if not in known enrichType list
     *
     * @param mixed $enrichType
     */
    public function setEnrichType($enrichType)
    {
        $this->enrichType = preg_replace('~[^0-9a-z\-_,]~i', '', $enrichType);
        if (strlen($this->enrichType) > 40) {
            $this->enrichType = substr($this->enrichType, 0, 40);
        }
    }

    protected function getDebugInfo($url, $jsonData)
    {
        return array(
            'requestUrl' => $url,
            'rawResponse' => $this->httpResponseRaw,
            'responseCode' => $this->httpResponseCode,
            'responseCodeClass' => $this->httpResponseCodeClass,
            'parsedResponse' => $jsonData,
            'httpClientError' => $this->httpClientError,
            'configuration' => array(
                'url' => $this->getServiceUrl(),
                'key' => $this->getKey(),
                'secret' => substr($this->getSecret(), 0, 6) . '[hidden]',
                'showcase' => $this->getStoreConfig('kvknl_api/advanced_config/api_showcase'),
                'debug' => $this->getStoreConfig('kvknl_api/advanced_config/api_debug'),
            ),
            'magentoVersion' => $this->getMagentoVersion(),
            'extensionVersion' => $this->getExtensionVersion(),
            'modules' => $this->getMagentoModules(),
        );
    }

    public function testConnection()
    {
        // Default is not OK
        /** @noinspection PhpUnusedLocalVariableInspection */
        $message = __('The test connection could not be successfully completed.');
        $status = 'error';
        $info = array();

        // Do a test address lookup
        $this->setDebuggingOverride(true);
        $addressData = $this->lookupAddress('VE source B.V');
        $this->setDebuggingOverride(false);

        if (!isset($addressData['debugInfo']) && isset($addressData['message'])) {
            // Client-side error
            $message = $addressData['message'];
            if (isset($addressData['info'])) {
                $info = $addressData['info'];
            }
        } else {
            if ($addressData['debugInfo']['httpClientError']) {
                // We have a HTTP connection error
                $message = __('Your server could not connect to the Kvk.nl server.');

                $info = $this->processHttpClientErrorInfo($addressData, $info);
            } else {
                if (!is_array($addressData['debugInfo']['parsedResponse'])) {
                    // We have not received a valid JSON response

                    $message = __('The response from the Kvk.nl service could not be understood.');
                    $info[] = '- ' . __('The service might be temporarily unavailable, if problems persist, '.
                            'please contact <a href=\'mailto:info@kvk.nl\'>info@kvk.nl</a>.');
                    $info[] = '- ' . __('Technical reason: No valid JSON was returned by the request.');
                } else {
                    if (is_array($addressData['debugInfo']['parsedResponse'])
                        && isset($addressData['debugInfo']['parsedResponse']['exceptionId'])
                    ) {
                        // We have an exception message from the service itself

                        if ($addressData['debugInfo']['responseCode'] == 401) {
                            $exceptionId = $addressData['debugInfo']['parsedResponse']['exceptionId'];
                            if ($exceptionId == self::EXCEPTION_NOT_AUTHORIZED) {
                                $message = __('`API Key` specified is incorrect.');
                            } else {
                                if ($exceptionId == self::EXCEPTION_PASSWORD_NOT_CORRECT) {
                                    $message = __('`API Secret` specified is incorrect.');
                                } else {
                                    $message = __('Authentication is incorrect.');
                                }
                            }
                        } else {
                            if ($addressData['debugInfo']['responseCode'] == 403) {
                                $message = __('Access is denied.');
                            } else {
                                $message = __('Service reported an error.');
                            }
                        }
                        $info[] = __('Kvk.nl service message:') . ' "' .
                            $addressData['debugInfo']['parsedResponse']['exception'] . '"';
                    } else {
                        if (is_array($addressData['debugInfo']['parsedResponse'])
                            && !isset($addressData['debugInfo']['parsedResponse']['kvk'])) {
                            // This message is thrown when the JSON returned did not contain the data expected.

                            $message = __('The response from the Kvk.nl service could not be understood.');
                            $info[] = '- ' . __('The service might be temporarily unavailable, if problems persist, '.
                                    'please contact <a href=\'mailto:info@kvk.nl\'>info@kvk.nl</a>.');
                            $info[] = '- ' . __('Technical reason: Received JSON data did not contain expected data.');
                        } else {
                            $message = __('A test connection to the API was successfully completed.');
                            $status = 'success';
                        }
                    }
                }
            }
        }

        return array(
            'message' => $message,
            'status' => $status,
            'info' => $info,
        );
    }

    protected function processHttpClientErrorInfo($addressData, $info)
    {
        // Do some common SSL CA problem detection
        if (strpos(
            $addressData['debugInfo']['httpClientError'],
            'SSL certificate problem, verify that the CA cert is OK'
        ) !== false) {
            $info[] = __('Your servers\' \'cURL SSL CA bundle\' is missing or outdated. Further information:');
            $info[] = '- <a href="https://stackoverflow.com/questions/6400300/https-and-ssl3-get-server-'.
                'certificatecertificate-verify-failed-ca-is-ok" target="_blank">'.
                __('How to update/fix your CA cert bundle') . '</a>';
            $info[] = '- <a href="https://curl.haxx.se/docs/sslcerts.html" target="_blank">'.
                __('About cURL SSL CA certificates') . '</a>';
            $info[] = '';
        } else {
            if (strpos(
                $addressData['debugInfo']['httpClientError'],
                'unable to get local issuer certificate'
            ) !== false) {
                $info[] = __('cURL cannot read/access the CA cert file:');
                $info[] = '- <a href="https://curl.haxx.se/docs/sslcerts.html" target="_blank">'.
                    __('About cURL SSL CA certificates') . '</a>';
                $info[] = '';
            } else {
                $info[] = __('Connection error.');
            }
        }
        $info[] = __('Error message:') . ' "' . $addressData['debugInfo']['httpClientError'] . '"';
        $info[] = '- <a href="https://www.google.com/search?q='.
            urlencode($addressData['debugInfo']['httpClientError']).
            '" target="_blank">' . __('Google the error message') . '</a>';
        $info[] = '- ' . __('Contact your hosting provider if problems persist.');
        return $info;
    }

    protected function getStoreConfig($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    protected function getKey()
    {
        $apiKey = trim($this->getStoreConfig('kvknl_api/general/api_key'))?trim($this->getStoreConfig('kvknl_api/general/api_key')):self::API_KEY;
        return $apiKey;
    }

    protected function getSecret()
    {
        return trim($this->getStoreConfig('kvknl_api/general/api_secret'));
    }

    protected function getServiceUrl()
    {
        $serviceUrl = trim($this->getStoreConfig('kvknl_api/development_config/api_url'));
        if (empty($serviceUrl)) {
            $serviceUrl = self::API_URL;
        }

        return $serviceUrl;
    }

    protected function getMagentoVersion()
    {
        if ($this->getModuleInfo('Enterprise_CatalogPermissions') !== null) {
            // Detect enterprise
            return 'MagentoEnterprise/' . $this->productMetadataInterface->getVersion();
        } elseif ($this->getModuleInfo('Enterprise_Enterprise') !== null) {
            // Detect professional
            return 'MagentoProfessional/' . $this->productMetadataInterface->getVersion();
        }

        return 'Magento/' . $this->productMetadataInterface->getVersion();
    }

    protected function getModuleInfo($moduleName)
    {
        $modules = $this->getMagentoModules();

        if (!isset($modules[$moduleName])) {
            return null;
        }

        return $modules[$moduleName];
    }

    protected function getConfigBoolString($configKey)
    {
        if ($this->getStoreConfig($configKey)) {
            return true;
        }

        return false;
    }

    protected function curlHasSsl()
    {
        $curlVersion = curl_version();
        return $curlVersion['features'] & CURL_VERSION_SSL;
    }

    protected function checkApiReady()
    {
        if (!$this->debuggingOverride
            && !($this->getStoreConfig('kvknl_api/general/enabled')
                || $this->getStoreConfig('kvknl_api/advanced_config/admin_validation_enabled')
            )
        ) {
            return array('message' => __('kvk API not enabled.'));
        }

        if ($this->getServiceUrl() === '' || $this->getKey() === '') {
            return array(
                'message' => __('kvk API not configured.'),
                'info' => array(__('Configure your `API key`'))
            );
        }

        return $this->checkCapabilities();
    }

    protected function checkCapabilities()
    {
        // Check for SSL support in CURL
        if (!$this->curlHasSsl()) {
            return array(
                'message' => __('Cannot connect to Kvk.nl API: Server is missing SSL (https) support for CURL.')
            );
        }

        return false;
    }

    protected function callApiUrlGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $this->httpResponseRaw = curl_exec($ch);
        $this->httpResponseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->httpResponseCodeClass = (int)floor($this->httpResponseCode / 100) * 100;
        $curlErrno = curl_errno($ch);
        $this->httpClientError = $curlErrno ? sprintf('cURL error %s: %s', $curlErrno, curl_error($ch)) : null;

        curl_close($ch);

        $decode = json_decode($this->httpResponseRaw, true);

        //Rebuild to JSON format for easyautocomplete
        $construct = [];

        //Rebuild to JSON format for easyautocomplete
        if (isset($decode['data']) && isset($decode['data']['items']))
        foreach($decode['data']['items']  as $key => $address) {

                // Kvk number fetch
                $kvkNumber = $address['kvkNumber'];
                // businessname fetch, if short available, else longname
                if (isset($address['tradeNames']['shortBusinessName'])){
                        $shortBusinessName = $address['tradeNames']['shortBusinessName'];
                }else {
                        $shortBusinessName = $address['tradeNames']['businessName'];
                }
                //If address available fetch for auto-fill
                if (isset($address['addresses'])){
                        $street = $address['addresses']['0']['street']; 
                        $houseNumber = $address['addresses']['0']['houseNumber'];
                        $houseNumberAddition = $address['addresses']['0']['houseNumberAddition'];
                                $postalCodeORG = $address['addresses']['0']['postalCode'];
                                preg_match_all('/(\d)|(\w)/', $postalCodeORG, $matches);
                                $numbers = implode($matches[1]);
                                $letters = implode($matches[2]);

                        $postalCode = ''. $numbers .' '. $letters .'';

                        $city = $address['addresses']['0']['city'];
                        $country = $address['addresses']['0']['country'];

                        // Construct the array
                        // only show if any array
                        $construct[] = array(
                                'kvk' => $kvkNumber, 
                                'shortBusinessName' => $shortBusinessName, 
                                'street' => $street, 
                                'houseNumber' => $houseNumber, 
                                'houseNumberAddition' => $houseNumberAddition, 
                                'postalCode' => $postalCode, 
                                'city' => $city, 
                                'country' => $country 
                        );

                }else{
                        $street = 0; 
                        $houseNumber = 0;
                        $houseNumberAddition = 0;
                        $postalCode = 0;
                        $city = 0;
                        $country = 0;
                }


        };
        //if no results


        if (empty($construct)) {

                $kvkNumber = 'probeer het nog eens';
                $shortBusinessName = 'Geen resultaat';
                                        // Construct the array empty

                $construct = array(
                        'kvk' => $kvkNumber, 
                        'shortBusinessName' => $shortBusinessName
                );

        }

        return $construct;		
    }

    protected function getExtensionVersion()
    {
        $extensionInfo = $this->getModuleInfo('KvkNl_Api');
        return $extensionInfo ? (string)$extensionInfo['version'] : 'unknown';
    }

    protected function getUserAgent()
    {
        return 'KvkNl_Api_MagentoPlugin/' . $this->getExtensionVersion() . ' ' .
            $this->getMagentoVersion() . ' PHP/' . phpversion() . ' EnrichType/' . $this->enrichType;
    }

    protected function getMagentoModules()
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $this->modules = array();

        foreach ($this->moduleList->getAll() as $name => $module) {
            $this->modules[$name] = array();
            foreach ($module as $key => $value) {
                if (in_array((string)$key, array('setup_version', 'name'))) {
                    $this->modules[$name][$key] = (string)$value;
                }
            }
        }

        return $this->modules;
    }
}
