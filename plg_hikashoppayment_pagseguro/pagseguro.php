<?php
/**
 * @package	Payment Pagseguro HikaShop 2.2 / Joomla 3.x
 * @version	1.1.0
 * @author	jobadoo.com.br
 * @copyright	(C) 2006-2014 Jobadoo Webdesign. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');

class plgHikashoppaymentPagseguro extends hikashopPaymentPlugin
{
	var $accepted_currencies = array(
		'AUD','CAD','EUR','GBP','JPY','USD','NZD','CHF','HKD','SGD',
		'SEK','DKK','PLN','NOK','HUF','CZK','MXN','BRL','MYR','PHP',
		'TWD','THB','ILS','TRY'
	);

	var $multiple = true;
	var $name = 'pagseguro';
	var $doc_form = 'pagseguro';

	function  __construct(&$subject, $config){
		return parent::__construct($subject, $config);
	}

	function onAfterOrderConfirm(&$order,&$methods,$method_id) {
		parent::onAfterOrderConfirm($order,$methods,$method_id);

		// adding PagSeguro API
		require_once 'PagSeguroLibrary/PagSeguroLibrary.php';

		$notify_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=pagseguro&tmpl=component&invoice='.$order->order_id;
		$return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id.$this->url_itemid;

		// performing PagSeguro transaction
		$pagSeguroPaymentRequest = $this->_generatePagSeguroRequestData($order, $notify_url, $return_url);
		$url = $this->_performPagSeguroRequest($pagSeguroPaymentRequest);

		$this->payment_params->url = $url;

		return $this->showPage('end');
	}
	function onPaymentNotification(&$statuses){
		// adding PagSeguro API
		require_once 'PagSeguroLibrary/PagSeguroLibrary.php';

		// retrieving configurated default log info
		$filename = JPATH_BASE . '/logs/log_pagseguro.log';
		$this->_verifyFile($filename);
		PagSeguroConfig::activeLog($filename);

		$order_id = (int)$_REQUEST['invoice'];
		$dbOrder = $this->getOrder($order_id);

		$this->loadPaymentParams($dbOrder);
		if(empty($this->payment_params))
			return false;

		$post = $_POST;

		if (!PagSeguroHelper::isNotificationEmpty($post)) {
			$notificationType = new PagSeguroNotificationType($post['notificationType']);
			$strType = $notificationType->getTypeFromValue();
			switch ($strType) {
				case 'TRANSACTION':
					$this->_doUpdateByNotification($statuses, $post['notificationCode']);
					break;
				default:
					LogPagSeguro::error("Unknown notification type [" . $notificationType->getValue() . "]");
			}

		} else {

			LogPagSeguro::error("Invalid notification parameters.");
		}
		return true;
	}

	function getPaymentDefaultValues(&$element) {
		$element->payment_name='Pagseguro';
		$element->payment_description='You can pay by Pagseguro using this payment method';
		$element->payment_images='';

		$element->payment_params->invalid_status='cancelled';
		$element->payment_params->verified_status='confirmed';
	}

	private function _generatePagSeguroRequestData($order, $notify_url, $return_url)
	{
		$paymentRequest = new PagSeguroPaymentRequest();
		$paymentRequest->setCurrency(PagSeguroCurrencies::getIsoCodeByName('REAL')); // currency
		$paymentRequest->setReference($order->order_id); // reference
		$paymentRequest->setRedirectURL($return_url); // redirect url
		$paymentRequest->setNotificationURL($notify_url); // notification url
		$paymentRequest->setItems($this->_generateProductsData($order)); // products
		$paymentRequest->setExtraAmount($this->_getExtraAmountValues($order)); // extra values

		$paymentRequest->setSender($this->_generateSenderData($order)); // sender

		$paymentRequest->setShipping($this->_generateShippingData($order)); // shipping

		return $paymentRequest;
	}

	/**
	 * Gets extra amount cart values (coupon and shipping)
	 * @param VirtueMartCart $cart
	 * @return float
	 */
	private function _getExtraAmountValues($order)
	{
		if(!empty($order->cart->coupon)){
			$coupon = (float)$order->order_discount_price;
		}

		return PagSeguroHelper::decimalFormat($coupon * (-1));
	}

	/**
	 * Generates products data to PagSeguro transaction
	 * @param VirtueMartCart $cart
	 * @return array
	 */
	private function _generateProductsData($order)
	{
		$weightClass=hikashop_get('helper.weight');
		$pagSeguroItems = array();

		$cont = 1;
		$class = hikashop_get('class.product');
		foreach($order->cart->products as $product){
			if(!$product->order_product_quantity) continue;

			$product_data = $class->get($product->product_id);
			if(!empty($product->product_parent_id) && $product_data->product_weight<=0){
				$product_data = $class->get($product->product_parent_id);
			}
			$product_weight=(int)$weightClass->convert($product_data->product_weight,$product_data->product_weight_unit,'g');

			$pagSeguroItem = new PagSeguroItem();
			$pagSeguroItem->setId($cont++);
			$pagSeguroItem->setDescription(strip_tags($product->order_product_name));
			$pagSeguroItem->setQuantity($product->order_product_quantity);
			$pagSeguroItem->setAmount(round(PagSeguroHelper::decimalFormat($product->order_product_price), 2));
			$pagSeguroItem->setWeight($product_weight); // defines weight in gramas

			array_push($pagSeguroItems, $pagSeguroItem);
		}

		return $pagSeguroItems;
	}

	/**
	 *  Generates sender data to PagSeguro transaction
	 *  @return PagSeguroSender
	 */
	private function _generateSenderData($order)
	{
		$pagSeguroSender = new PagSeguroSender();

		$app = JFactory::getApplication();
		$cart = hikashop_get('class.cart');
		$user = hikashop_loadUser(true);

		$address=$app->getUserState( HIKASHOP_COMPONENT.'.shipping_address');
		if(!empty($address)){
			$cart->loadAddress($order->cart,$address,'object','shipping');
			$pagSeguroSender->setEmail($user->user_email);
			$pagSeguroSender->setName(@$order->cart->shipping_address->address_firstname . ' ' . @$order->cart->shipping_address->address_lastname);
		}
		return $pagSeguroSender;
	}

	/**
	 * Generates shipping data to PagSeguro transaction
	 * @param stdClass $deliveryAddress
	 * @param float $shippingCost
	 * @return \PagSeguroShipping
	 */
	private function _generateShippingData($order)
	{

		$shipping = new PagSeguroShipping();
		$shipping->setAddress($this->_generateShippingAddressData($order));
		$shipping->setType($this->_generateShippingType());
		$shipping->setCost(PagSeguroHelper::decimalFormat((float) $order->order_shipping_price));

		return $shipping;
	}

	/**
	 *  Generate shipping type data to PagSeguro transaction
	 *  @return PagSeguroShippingType
	 */
	private function _generateShippingType()
	{
		$shippingType = new PagSeguroShippingType();
		$shippingType->setByType('NOT_SPECIFIED');

		return $shippingType;
	}

	/**
	 *  Generates shipping address data to PagSeguro transaction
	 *  @return PagSeguroAddress
	 */
	private function _generateShippingAddressData($order)
	{

		$address = new PagSeguroAddress();

		$app = JFactory::getApplication();
		$cart = hikashop_get('class.cart');

		$shipping_address=$app->getUserState( HIKASHOP_COMPONENT.'.shipping_address');
		if(!empty($shipping_address)){
			$cart->loadAddress($order->cart,$shipping_address,'object','shipping');

			$address->setCity(@$order->cart->shipping_address->address_city);
			$address->setPostalCode(@$order->cart->shipping_address->address_post_code);
			$address->setStreet(@$order->cart->shipping_address->address_street);

			$address2 = '';
				if(!empty($order->cart->shipping_address->address_street2)){
					$address2 = substr($order->cart->shipping_address->address_street2,0,99);
				}
			$address->setDistrict($address2);

			$address->setCountry(@$order->cart->shipping_address->address_country->zone_code_3);
			$address->setState(@$order->cart->shipping_address->address_state->zone_code_3);
		}
		return $address;
	}

	/**
	 *  Perform PagSeguro request and return url from PagSeguro
	 *  @return string
	 */
	private function _performPagSeguroRequest(PagSeguroPaymentRequest $pagSeguroPaymentRequest)
	{

		try
		{
			// setting PagSeguro configurations
			$this->_setPagSeguroConfiguration();

			// setting PagSeguro plugin version
			$this->_setPagSeguroModuleVersion();

			// setting VirtueMart version
			$this->_setPagSeguroCMSVersion();

			// getting credentials
			$credentials = new PagSeguroAccountCredentials($this->payment_params->pagseguro_email, $this->payment_params->pagseguro_token);

			// return performed PagSeguro request values
			return $pagSeguroPaymentRequest->register($credentials);

		}
		catch (PagSeguroServiceException $e)
		{
			die($e->getMessage());
		}

	}

	/**
	 * Retrieve PagSeguro data configuration from database
	 */
	private function _setPagSeguroConfiguration()
	{

		// retrieving configurated default charset
		PagSeguroConfig::setApplicationCharset('UTF-8');

		// retrieving configurated default log info
		$filename = JPATH_BASE . '/logs/log_pagseguro.log';
		$this->_verifyFile($filename);
		PagSeguroConfig::activeLog($filename);
	}

	/**
	 * Sets PagSeguro plugin version
	 */
	private function _setPagSeguroModuleVersion()
	{
		PagSeguroLibrary::setModuleVersion('hikashop' . ':1.0');
	}

	/**
	 * Sets VirtueMart version
	 */
	private function _setPagSeguroCMSVersion()
	{
		PagSeguroLibrary::setCMSVersion('hikashop' . ':2.1.0');
	}

	/**
	 * Try create file if not exists
	 * @param string $filename
	 */
	private function _verifyFile($filename)
	{

		try
		{
			$f = fopen($filename, 'a');
			fclose($f);
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}
	}

	/**
	 * Perform update by received PagSeguro notification
	 * @param string $notificationCode
	 */
	private function _doUpdateByNotification($statuses, $notificationCode)
	{
		try
		{
			// getting credentials data
			$credentials = new PagSeguroAccountCredentials($this->payment_params->pagseguro_email, $this->payment_params->pagseguro_token);

			// getting transaction data object
			$transaction = PagSeguroNotificationService::checkTransaction($credentials, $notificationCode);
			// getting PagSeguro status number
			$statusPagSeguro = $transaction->getStatus()->getValue();

			$array_status = array(0 => 'Initiated', 1 => 'Waiting payment', 2 => 'In analysis', 3 => 'Paid', 4 => 'Available', 5 => 'In dispute', 6 => 'refunded', 7 => 'cancelled');

			// performing update status
			if (!PagSeguroHelper::isEmpty($statusPagSeguro) && (int)$statusPagSeguro == 3) {
				$orderClass = hikashop_get('class.order');
				$dbOrder = $orderClass->get((int)$transaction->getReference());

				$email = new stdClass();
				$history = new stdClass();

				$order_status = $this->payment_params->verified_status;
				$history->notified=1;

				if($dbOrder->order_status == $order_status)
					return true;

				$url = HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id='.$dbOrder->order_id;
				$order_text = "\r\n".JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE',$dbOrder->order_number,HIKASHOP_LIVE);
				$order_text .= "\r\n".str_replace('<br/>',"\r\n",JText::sprintf('ACCESS_ORDER_WITH_LINK',$url));

				$mail_status=$statuses[$order_status];
				$email->subject = JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER','Pagseguro', $array_status[$statusPagSeguro],$dbOrder->order_number);
				$email->body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','Pagseguro',$array_status[$statusPagSeguro])).' '.JText::sprintf('ORDER_STATUS_CHANGED',$mail_status)."\r\n\r\n".$order_text;

				$this->modifyOrder($dbOrder->order_id,$order_status,$history,$email);

			} elseif ((int)$statusPagSeguro == 7) {
				$orderClass = hikashop_get('class.order');
				$dbOrder = $orderClass->get((int)$transaction->getReference());

				$email = new stdClass();
				$history = new stdClass();

				$order_status = $this->payment_params->invalid_status;
				$history->notified=0;

				if($dbOrder->order_status == $order_status)
					return true;

				$url = HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id='.$dbOrder->order_id;
				$order_text = "\r\n".JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE',$dbOrder->order_number,HIKASHOP_LIVE);
				$order_text .= "\r\n".str_replace('<br/>',"\r\n",JText::sprintf('ACCESS_ORDER_WITH_LINK',$url));

				$mail_status=$statuses[$order_status];
				$email->subject = JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER','Pagseguro', $array_status[$statusPagSeguro],$dbOrder->order_number);
				$email->body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','Pagseguro',$array_status[$statusPagSeguro])).' '.JText::sprintf('ORDER_STATUS_CHANGED',$mail_status)."\r\n\r\n".$order_text;

				$this->modifyOrder($dbOrder->order_id,$order_status,$history,$email);

			}
		}
		catch (PagSeguroServiceException $e)
		{
			LogPagSeguro::error("Error trying get transaction [" . $e->getMessage() . "]");
		}

		return true;
	}

}