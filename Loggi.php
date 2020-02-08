<?php

namespace Kaisari\Loggi\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;

/**
 * Custom shipping model
 */
class Loggi extends AbstractCarrier implements CarrierInterface {
    /**
     * @var string
     */
    protected $_code = 'loggi';

    /**
     * @var bool
     */
    protected $_isFixed = TRUE;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private $rateMethodFactory;


    /**
     *
     * @var string
     */
    private $urlProduction = "https://www.loggi.com";

    /**
     *
     * @var string
     */
    private $urlDevelopment = "https://staging.loggi.com";

    /**
     *
     * @var string
     */
    private $apiKey;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;

        if($this->getConfigFlag('active')) {
            $this->login();
        }
    }

    /**
     * Custom Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request) {
        if(!$this->getConfigFlag('active')) {
            return FALSE;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        if($response = $this->getEstimativa($request)) {
            if (isset($response['errors'])) {
                return false;
            }
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));

            $method->setMethod($this->_code);
            $method->setMethodTitle($this->getConfigData('name'));

            $shippingCost = $response['normal']['estimated_cost'];

            $method->setPrice($shippingCost);
            $method->setCost($shippingCost);

            $result->append($method);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods() {
        return [$this->_code => $this->getConfigData('name')];
    }

    private function getEstimativa(RateRequest $request) {
        $url = $this->getUrl() . "/api/v1/endereco/estimativa/";

        $way_point = [
            [
                "by"    => "cep",
                "query" => [
                    "cep"          => preg_replace("/[^0-9]/", "", $request->getPostcode()),
                    "instructions" => "Estimate Only :)",
                    "number"       => 0
                ]
            ],
            [
                "by"    => "cep",
                "query" => [
                    "cep"          => preg_replace("/[^0-9]/", "", $request->getDestPostcode()),
                    "instructions" => "Estimate Only :)",
                    "number"       => 0
                ]
            ]
        ];

        $data = json_encode([
                                'city'           => $this->getConfigData('city'),
                                'transport_type' => '1',
                                'addresses'      => $way_point
                            ]);

        $headers = array(
            "Authorization: ApiKey " . $this->getConfigData('email') . ":" . $this->apiKey,
            "Content-Type: application/json;charset=UTF-8"
        );

        $response = $this->executeCurl($url, $data, $headers);

        $response = json_decode($response, TRUE);

        if(isset($response["error_message"])) {
            throw new Exception($response["error_message"]);
        }
        // print_r($response);
        return $response;
    }

    private function getUrl() {
        if($this->getConfigFlag('sandbox')) {
            return $this->urlDevelopment;
        } else {
            return $this->urlProduction;
        }
    }

    private function login() {
        $url = $this->getUrl();
        $url = $url . '/api/v1/usuarios/login/';

        $data = json_encode(['user' => [
            'password' => $this->getConfigData('password'),
            'email'    => $this->getConfigData('email')
        ]]);

        $headers = [
            "Content-Type: application/json;charset=UTF-8"
        ];

        $response = $this->executeCurl($url, $data, $headers);

        $response = json_decode($response);

        if($response->success) {
            $this->apiKey = $response->api_key;
        }
    }

    private function executeCurl($url, $data, $headers, $isPost = TRUE) {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        if($isPost) {
            curl_setopt($curl, CURLOPT_POST, TRUE);
        }

        if($headers != NULL) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);

        if($data != NULL) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $curl_response = curl_exec($curl);
        if(curl_errno($curl)) {
            $this->_logger->error('curl error: ' . curl_error($curl));
            return FALSE;
        }
        curl_close($curl);

        return $curl_response;
    }
}
