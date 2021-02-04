<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace jmodz\Upsfreightshipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Store\Model\ScopeInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;


class Upsfreightshipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{

    protected $_code = 'upsfreightshipping';

    protected $_isFixed = true;

    protected $_rateResultFactory;

    protected $_rateMethodFactory;

    protected $_request;

    protected $_rawRequest;

    /**
     * Constructor
     *
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
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    protected function _getCarrierCode(){
		return $this->_code;
	}

    /**
     * {@inheritdoc}
     */
    public function collectRates(RateRequest $request)
    {

        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $this->setRequest($request);

        $this->_result = $this->_getQuotes(false);
        if ($this->getConfigFlag('showResidentialFee')){
			//sMage::log('showing residential fee..........');
			$this->_result->append($this->_getQuotes(true));
		}
        //$shippingPrice = $this->getConfigData('price');

        //$result = $this->_rateResultFactory->create();

        /*if ($shippingPrice !== false) {
            $method = $this->_rateMethodFactory->create();

            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));

            $method->setMethod($this->_code);
            $method->setMethodTitle($this->getConfigData('name'));

            if ($request->getFreeShipping() === true || $request->getPackageQty() == $this->getFreeBoxes()) {
                $shippingPrice = '0.00';
            }

            $method->setPrice($shippingPrice);
            $method->setCost($shippingPrice);

            $result->append($method);
        }*/

        return $this->_result;
    }

	public function _getQuotes($showResidentialRate) {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

		// if fixed rate is used, don't call the xml service.  Use handling fees to obtain rates

		if ($this->getConfigFlag('usefixedrate')){
			$expectedArrivalDate = strftime("%a, %m/%d/%Y", mktime(0, 0, 0, intval(date("m"))  , date("d")+$this->getConfigData('adddaystodeliverydate'), intval(date("Y"))));
			
			$result = $this->_rateResultFactory->create();
			$result->append($this->buildRate($expectedArrivalDate, 0, $this->getConfigData('methodtext'), 0, 0));
			return $result;
		}

		$isTestMode = $this->getConfigFlag('testMode');
		if ($isTestMode) {
			$xmlRequest = $this->_buldTestXmlRequest();
		}else{
			$xmlRequest = $this->_buldProdXmlRequest();
		}

		$responseBody = $this->_callService($xmlRequest);

		return $this->_parseXmlResponse($xmlRequest, $responseBody, $showResidentialRate, $isTestMode);
	}

	public function _callService($xmlRequest){

		if ( $this->getConfigFlag('testMode')){
			$gateway_url = $this->getConfigData('test_gateway_url');
		}
		else{
			$gateway_url = $this->getConfigData('prod_gateway_url');
		}

		$xmlrequestcontext = $this->_buildContext($xmlRequest);
		//echo $xmlrequestcontext;die;

		$fp = @fopen($gateway_url, 'rb', false, $xmlrequestcontext);

		//echo gettype($fp);die;
		$serviceResponse = @stream_get_contents($fp);
//print_r($serviceResponse);die;
		return $serviceResponse;
	}

		
	
	public function _parseXmlResponse($xmlRequest, $response, $showResidentialRate, $isTestMode) {
		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/uspfreight.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer);

		$response = str_replace(':', '_', $response);
		$netFreightCharge = null;
		$xml = null;
		//echo "<pre>";print_r($response);exit;
		$logger->info('XML Response'.$response);
		if (!is_null($response)) {
			$xml = simplexml_load_string($response);

			if (isset($response) && !is_null($xml) && $xml !== '' && is_null($xml->soapenv_Body->soapenv_Fault->faultcode)){
	
				if ($isTestMode){
					$rateObj = $xml->soapenv_Body->freightRate_FreightRateResponse->freightRate_TotalShipmentCharge;
					$netFreightCharge =  floatval($rateObj->freightRate_MonetaryValue);
				}else{
					$rateObj = $xml->soapenv_Body->freightRate_FreightRateResponse->freightRate_TotalShipmentCharge;
					$netFreightCharge =  floatval($rateObj->freightRate_MonetaryValue);
				}
				$logger->info("The returned amount is : ".$netFreightCharge);
			}
			else {
				$logger->info("Unable to load xml from response.");
				$logger->info('response='.$response);
			}
		}
		else {
			$logger->info("XML Response was null.");
		}
		$result = $this->_rateResultFactory->create();
		//print_r($netFreightCharge);die;
		//$result->append($this->buildRate($expectedArrivalDate, 0, $this->getConfigData('methodtext'), 0, 0));
		//$result = Mage::getModel('shipping/rate_result');
		if (!isset($netFreightCharge) || is_null($netFreightCharge) || $netFreightCharge == "") {
			//Mage::log("Rate Request error occurred. See request/response below:");
			//Mage::log('XML Request'.$xmlRequest);
			//Mage::log('XML Response'.$response);
$logger->info("Rate Request error occurred. See request/response below:");
$logger->info('XML Request'.$xmlRequest);
$logger->info('XML Response'.$response);
			// don't show the error twice
			if (!$showResidentialRate){

				$error  = $this->_rateErrorFactory->create();
				$error->setCarrier('ups');
				$error->setMethod($this->_code);
				$error->setCarrierTitle($this->getConfigData('title'));
				//$error->setErrorMessage($errorTitle);
				$error->setErrorMessage($this->getConfigData('specificerrmsg'));
				$result->append($error);

				$error = $this->_rateErrorFactory->create();
				$error->setCarrier('upsfreight');
				$error->setMethod($this->_code);
				$error->setCarrierTitle($this->getConfigData('title'));
				$error->setErrorMessage($this->getConfigData('specificerrmsg'));
				$result->append($error);
			}
		}
		else {

			$maxCost = $this->getConfigData('max_freight_cost');

			if ($netFreightCharge > $maxCost) {
				$netFreightCharge = $maxCost;
			}

			if ($isTestMode){
				$result->append($this->buildRate(0, $netFreightCharge, 'TEST MODE - Use this value when requesting your UPS Freight production access key.  You can change the mode within the shipping method\'s configuration', 0, 0));
			}
			else{
				if ($showResidentialRate){
					$result->append($this->buildRate(0, $netFreightCharge, $this->getConfigData('residentialratetext'),  $this->getConfigData('residentialfee'), 1));
				}
				else {
					$result->append($this->buildRate(0, $netFreightCharge, $this->getConfigData('methodtext'), 0, 0));
				}
			}
		}
		$freightCharge = $netFreightCharge;
		return $result;
	}

		public function _buildContext($xmlRequest){
		$header = <<<HEAD
Content-Type: text/xml; charset=utf-8
SOAPAction: 'http://onlinetools.ups.com/webservices/FreightRateBinding'
HEAD;

		$paramAry = Array(
			'http' => Array(
				'method' => "POST",
				'header' => $header,
				'content' => $xmlRequest ));

		return stream_context_create($paramAry);
	}


	public function _buldProdXmlRequest(){
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$encyptor = $objectManager->get('Magento\Framework\Encryption\EncryptorInterface');
		$r = $this->_rawRequest;
		$user = $this->getConfigData('token_username');
		$pass = $encyptor->decrypt($this->getConfigData('token_password'));
		$key = $encyptor->decrypt($this->getConfigData('key'));
		$securityHeader = $this->buildSecurity($user,$pass,$key);
//echo $this->getConfigData('palletweight');die;
		$itemWeight = intval($r->getWeight())+$this->getConfigData('palletweight');
		$shipFrom = $this->buildAddress('', '', '', '', $r->getOrigPostal(), $r->getOrigCountry());
		$shipTo = $this->buildAddress('', '', '', '', $r->getDestPostal(), $r->getDestCountry());
		$payer = $this->buildAddress('Payer', $this->getConfigData('billto_address'), $this->getConfigData('billto_city'), $this->getConfigData('billto_state'), $this->getConfigData('billto_zip'), $this->getConfigData('billto_country'));
		$shipmentBillingOptionCode = $this->getConfigData('requestor_type');
		$shipmentBillingOptionDesc = $this->getConfigData('requestor_type');
		$freightClass = $this->getConfigData('freight_class');

		return $this->_constructXMLRequest($securityHeader, $shipFrom, $shipTo, $payer, $shipmentBillingOptionCode, $shipmentBillingOptionDesc, $itemWeight, $freightClass);
	}

	public function _buldTestXmlRequest(){
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$encyptor = $objectManager->get('Magento\Framework\Encryption\EncryptorInterface');
		$r = $this->_rawRequest;
		$user = $this->getConfigData('token_username');
		$pass = $encyptor->decrypt($this->getConfigData('token_password'));
		$key = $encyptor->decrypt($this->getConfigData('key'));
		$securityHeader = $this->buildSecurity($user,$pass,$key);

		$itemWeight = '1500';
		$shipFrom = $this->buildAddress('Developer Test 1', '101 Developer Way', 'Richmond', 'VA', '23224', 'US');
		$shipTo = $this->buildAddress('Consignee Test 1', '1000 Consignee Street', 'Allanton', 'MO', '63001', 'US');
		$payer = $this->buildAddress('Developer Test 1', '101 Developer Way', 'Richmond', 'VA', '23224', 'US');
		$shipmentBillingOptionCode = '10';
		$shipmentBillingOptionDesc = 'PREPAID';
		$freightClass = '92.5';

		return $this->_constructXMLRequest($securityHeader, $shipFrom, $shipTo, $payer, $shipmentBillingOptionCode, $shipmentBillingOptionDesc, $itemWeight, $freightClass);
	}

	public function buildSecurity($userName, $password, $accessLicenseNumber){
		return  '
			<q0:UPSSecurity>
				<q0:UsernameToken>
					<q0:Username>'.$userName.'</q0:Username>
					<q0:Password>'.$password.'</q0:Password>
				</q0:UsernameToken>
				<q0:ServiceAccessToken>
					<q0:AccessLicenseNumber>'.$accessLicenseNumber.'</q0:AccessLicenseNumber>
				</q0:ServiceAccessToken>
			</q0:UPSSecurity>';
	}

	public function buildAddress($name, $addressLine, $city, $stateProvinceCode, $postalCode, $countryCode){
		return '
	        <q1:Name>'.$name.'</q1:Name>
	        <q1:Address>
	          <q1:AddressLine>'.$addressLine.'</q1:AddressLine>
	          <q1:City>'.$city.'</q1:City>
	          <q1:StateProvinceCode>'.$stateProvinceCode.'</q1:StateProvinceCode>
	          <q1:PostalCode>'.$postalCode.'</q1:PostalCode>
	          <q1:CountryCode>'.$countryCode.'</q1:CountryCode>
	        </q1:Address>';
	}

	public function _constructXMLRequest($securityHeader, $shipFrom, $shipTo, $payer, $shipmentBillingOptionCode, $shipmentBillingOptionDesc, $itemWeight, $freightClass){
		return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
			xmlns:q0="http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0" xmlns:q1="http://www.ups.com/XMLSchema/XOLTWS/FreightRate/v1.0"
			xmlns:q2="http://www.ups.com/XMLSchema/XOLTWS/Common/v1.0" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
			xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
			<soapenv:Header>
				'.$securityHeader.'
			</soapenv:Header>
			<soapenv:Body>
				<q1:FreightRateRequest>
					<q2:Request />
					<q1:ShipFrom>
						'.$shipFrom.'
					</q1:ShipFrom>
					<q1:ShipTo>
						'.$shipTo.'
					</q1:ShipTo>
					<q1:PaymentInformation>
						<q1:Payer>
							'.$payer.'
						</q1:Payer>
						<q1:ShipmentBillingOption>
							<q1:Code>'.$shipmentBillingOptionCode.'</q1:Code>
							<q1:Description>'.$shipmentBillingOptionDesc.'</q1:Description>
						</q1:ShipmentBillingOption>
					</q1:PaymentInformation>
					<q1:Service>
						<q1:Code>308</q1:Code>
						<q1:Description>UPS Freight LTL</q1:Description>
					</q1:Service>
					<q1:HandlingUnitOne>
						<q1:Quantity>1</q1:Quantity>
						<q1:Type>
							<q1:Code>PLT</q1:Code>
							<q1:Description>PALLET</q1:Description>
						</q1:Type>
					</q1:HandlingUnitOne>
					<q1:Commodity>
						<q1:Description>item</q1:Description>
						<q1:Weight>
							<q1:Value>'.$itemWeight.'</q1:Value>
							<q1:UnitOfMeasurement>
								<q1:Code>LBS</q1:Code>
								<q1:Description>Pounds</q1:Description>
							</q1:UnitOfMeasurement>
						</q1:Weight>
						<q1:NumberOfPieces>1</q1:NumberOfPieces>
						<q1:PackagingType>
							<q1:Code>PLT</q1:Code>
						</q1:PackagingType>
						<q1:FreightClass>'.$freightClass.'</q1:FreightClass>
					</q1:Commodity>
				</q1:FreightRateRequest>
			</soapenv:Body>
		</soapenv:Envelope>';

	}

	public function getMethodPrice($cost, $method='')
	{
		if ($method == $this->getConfigData('free_method') &&
		$this->getConfigData('free_shipping_enable') &&
		$this->getConfigData('free_shipping_subtotal') < $this->_rawRequest->getValueWithDiscount())
		{
			$price = '0.00';
		} else {

			//$priceWithFee1 = $this->getFinalPriceWithHandlingFees($cost, $this->getConfigData('handling1_fee'), $this->getConfigData('handling1_type'), $this->getConfigData('handling1_action'));
			$price = $this->getFinalPriceWithHandlingFees($cost, $this->getConfigData('handling1_fee'), $this->getConfigData('handling1_type'), $this->getConfigData('handling1_action'));

			//$price = $this->getFinalPriceWithHandlingFees($priceWithFee1, $this->getConfigData('handling2_fee'), $this->getConfigData('handling2_type'), $this->getConfigData('handling2_action'));
		}
		return $price;
	}

	public function getFinalPriceWithHandlingFees($cost, $handlingFee, $handlingType, $handlingAction)
	{
		//$handlingFee = $this->getConfigData('handling2_fee');
		$finalMethodPrice = 0;
		//$handlingType = $this->getConfigData('handling2_type');
		if (!$handlingType) {
			$handlingType = self::HANDLING_TYPE_FIXED;
		}
		//$handlingAction = $this->getConfigData('handling2_action');
		if (!$handlingAction) {
			$handlingAction = self::HANDLING_ACTION_PERORDER;
		}
		if($handlingAction == self::HANDLING_ACTION_PERPACKAGE)
		{
			if ($handlingType == self::HANDLING_TYPE_PERCENT) {
				$finalMethodPrice = ($cost + ($cost * $handlingFee/100));
			} else {
				$finalMethodPrice = ($cost + $handlingFee);
			}
		} else {
			if ($handlingType == self::HANDLING_TYPE_PERCENT) {
				$finalMethodPrice = ($cost) + ($cost * $handlingFee/100);
			} else {
	
				$finalMethodPrice = ($cost ) + $handlingFee;
			}
		}
		return $finalMethodPrice;
	}

	public function buildRate($expectedArrivalDateString, $netFreightChargeAmt, $methodText, $rDeliveryAmt, $idx) {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$method = $this->_rateMethodFactory->create();
		//$method = $rate['service'];
		$method->setCarrier($this->_getCarrierCode());
		$method->setCarrierTitle($this->getConfigData('title'));
		$method->setMethod($this->_code."_".$idx);
		$deliveryDateMsg = "";
		if ($this->getConfigFlag('showdeliverydatemsg')){
			$deliveryDateMsg =	htmlspecialchars("(Expected delivery by $expectedArrivalDateString)");
		}

		$method->setMethodTitle($methodText." ".$deliveryDateMsg);
		$method->setCost($this->getMethodPrice($netFreightChargeAmt+$rDeliveryAmt, '') );
		$method->setPrice($this->getMethodPrice($netFreightChargeAmt+$rDeliveryAmt, ''));

		return $method;
	}

    public function setRequest(RateRequest $request) {
    	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$this->_request = $request;
		$r = new \Magento\Framework\DataObject();


		if ($request->getOrigCountry()) {
			$origCountry = $request->getOrigCountry();
		} else {
			$origCountry = $this->_scopeConfig->getValue('shipping/origin/country_id', ScopeInterface::SCOPE_STORE);
		}

		//$r->setOrigCountry($productCollection = $objectManager->get('Magento\Directory\Model\Country')->load($origCountry)->getIso2Code());
		$r->setOrigCountry($origCountry);


		if ($request->getOrigRegionCode()) {
			$origRegionCode = $request->getOrigRegionCode();
		} else {
			$origRegionCode = $this->_scopeConfig->getValue('shipping/origin/region_id', ScopeInterface::SCOPE_STORE); 
			if (is_numeric($origRegionCode)) {
				$origRegionCode = $objectManager->get('Magento\Directory\Model\Region')->load($origRegionCode)->getCode();
			}
		}

		$r->setOrigRegionCode($origRegionCode);

		if ($request->getOrigPostcode()) {
			$r->setOrigPostal($request->getOrigPostcode());
		} else {
			$r->setOrigPostal($this->_scopeConfig->getValue('shipping/origin/postcode', ScopeInterface::SCOPE_STORE));
		}

		if ($request->getOrigCity()) {
			$r->setOrigCity($request->getOrigCity());
		} else {
			$r->setOrigCity($this->_scopeConfig->getValue('shipping/origin/city', ScopeInterface::SCOPE_STORE));
		}

		if ($request->getDestCountryId()) {
			$destCountry = $request->getDestCountryId();
		} else {
			$destCountry = AbstractCarrierOnline::USA_COUNTRY_ID;
		}

		//for UPS, puero rico state for US will assume as puerto rico country
		if ($destCountry == AbstractCarrierOnline::USA_COUNTRY_ID && ($request->getDestPostcode() == '00912' || $request->getDestRegionCode() == AbstractCarrierOnline::PUERTORICO_COUNTRY_ID)) {
			$destCountry = AbstractCarrierOnline::PUERTORICO_COUNTRY_ID;
		}
		//echo $destCountry;die;

		$r->setDestCountry($destCountry);

		$r->setDestRegionCode($request->getDestRegionCode());

		if ($request->getDestPostcode()) {
			$r->setDestPostal($request->getDestPostcode());
		} else {

		}

		$weight = $this->getTotalNumOfBoxes($request->getPackageWeight());
		$r->setWeight($weight);

		$this->_rawRequest = $r;

		return $this;
	}

    /**
     * getAllowedMethods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }
}
