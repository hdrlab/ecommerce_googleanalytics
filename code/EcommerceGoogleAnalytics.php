<?php

/** 
 * The main class for Ecommerce Google Analytics
 *
 * @author: Jeremy Shipman (original version)
 * @author: Hans de Ruiter
 *
 * @package: ecommerce-modules
 * @sub-package: ecommerce_googleanalytics
 */
class EcommerceGoogleAnaltyics{
	
	private static $storeName = null;
	
	static function setStoreName($name){
		self::$storeName = $name;
	}
	
	function submitTrackingData(Order $order){
		$accountid = GoogleConfig::get_google_config('code');
		if($accountid && self::needToSyncData($order)){
			$orderIsCancelled = $order->IsCancelled();
			
			// Cancelled orders need to be subtracted
			$multiplier = ($orderIsCancelled ? -1 : 1);
			
			$orderid = $order->ID;
			$storename = self::formatforjs(self::getStoreName());
			$total = $multiplier * $order->Total();
			
			//Taz
			$taxInfo = $order->TaxInfo();
			if($taxInfo && $taxInfo->Exists()){
				$tax = self::formatforjs($multiplier * $taxInfo->Charge);
			}
			else{
				$tax = self::formatforjs(null);
			}
			
			// Shipping
			$shipping = self::formatforjs($multiplier * 0);//TODO: shipping ammount
			
			$city = self::formatforjs(self::getOrderCity($order));
			
			$region = $order->getRegion();
			if($region) {
				$state = self::formatforjs($region->Name);
			}
			else {
				$state = self::formatforjs(null);
			}
			$country = self::formatforjs($order->FullNameCountry());
			
			$orderitems = $order->Items();
			
			if(!$orderid || !$total || !$orderitems)
				return;
			
			$script = <<<JS
				var _gaq = _gaq || [];
				_gaq.push(['_setAccount', "$accountid"]);
				_gaq.push(['_trackPageview']);
				_gaq.push(['_addTrans',
					"$orderid",           // order ID - required
					$storename,  // affiliation or store name
					"$total",          // total - required
					$tax,           // tax
					$shipping,              // shipping
					$city,       // city
					$state,     // state or province
					$country             // country
				]);

JS;

			foreach($orderitems as $item){
								
				$sku = self::getSKU($item);
				$name = self::formatforjs(self::getName($item));
				$quantity = self::formatforjs($multiplier * $item->Quantity);
				$unitprice = $item->UnitPrice();
				$variation = self::formatforjs(null); //TODO: variations support
		
				// add item might be called for every item in the shopping cart
				// where your ecommerce engine loops through each item in the cart and
				// prints out _addItem for each
				$script .= <<<JS
					_gaq.push(['_addItem',
						"$orderid",     // order ID - required
						"$sku",         // SKU/code - required
						$name,        // product name
						$variation,   // category or variation
						"$unitprice",   // unit price - required
						$quantity     // quantity - required
					]);
											
JS;

			}
			
			$script .= <<<JS
				_gaq.push(['_trackTrans']); //submits transaction to the Analytics servers

JS;

			if($orderIsCancelled) {
				$order->AnalyticsSubmitted = 'Cancelled';
			}
			else {
				$order->AnalyticsSubmitted = 'Yes';
			}
			$order->write();

			Requirements::customScript($script,'ecommercegoogleanalytics');
		}		
	}
	
	/**
	 * Returns true if the given order's current state needs to be sent to
	 * Google Analytics.
	 */
	static function needToSyncData(Order $order){
		if($order->IsCancelled()) {
			if($order->AnalyticsSubmitted != 'Cancelled'){
				return true;
			}
		}
		else if($order->IsCompleted() && $order->AnalyticsSubmitted != 'Yes'){
			return true;
		}

		return false;
	}
	
	static protected function formatforjs($val){
		return ($val) ? "\"$val\"" : "null";
	}
	
	static protected function getStoreName(){
		return self::$storeName;
	}
	
	static protected function getSKU($item){
		$buyable = $item->Buyable();
		
		$sku = $buyable->InternalItemID;
		if(!$sku) { 
			
			$sku = $item->BuyableClassName() . '-' . $buyable->ID; 
		}
		return $sku;
	}
	
	static protected function getName($item){
		return $item->BuyableTitle();
	}
	
	/** 
	 * Return's the order's city.
	 * 
	 * TODO: This probably should be moved to inside the Order class itself,
	 * just like the region and country get functions.
	 */
	static protected function getOrderCity(Order $order){
		$cityNames = array(
			"Billing" => null,
			"Shipping" => null
		);
		if($order->BillingAddressID) {
			if($billingAddress = $order->BillingAddress()) {
				$cityNames["Billing"] = $billingAddress->City;
			}
		}
		if($order->CanHaveShippingAddress()) {
			if($order->ShippingAddressID) {
				if($shippingAddress = $order->ShippingAddress()) {
					$cityNames["Shipping"] = $shippingAddress->ShippingCity;
				}
			}
		}
		if(count($cityNames)) {
			//note the double-check with $order->CanHaveShippingAddress() and get_use_....
			if($order->CanHaveShippingAddress() && OrderAddress::get_use_shipping_address_for_main_region_and_country() && $cityNames["Shipping"]) {
				return $cityNames["Shipping"];
			}
			else {
				return $cityNames["Billing"];
			}
		}
	}
	
}

/**
 * Extends OrderConfirmationPage_Controller to enable submitting tracking information
 * to Google Analytics.
 */
class EGAOrderConfirmationPage extends Extension {
	
	function onAfterInit(){
		$order = $this->owner->Order();
		if($order){
			EcommerceGoogleAnaltyics::submitTrackingData($order);
		}
	}
}

/**
 * Extends AccountPage_Controller to enable submitting tracking information
 * to Google Analytics.
 */
class EGAAccountPage extends Extension {
	
	function onAfterInit(){
		if(!Member::CurrentMember()) { return; }
		// Quickly scan through the past orders for any that are not yet submitted
		// to Google Analytics, and submit the first one that is found (if any)
		$pastOrders = $this->owner->PastOrders();
		if($pastOrders && $pastOrders->Count() > 0){
			foreach($pastOrders as $order) {
				if(EcommerceGoogleAnaltyics::needToSyncData($order)) {
					EcommerceGoogleAnaltyics::submitTrackingData($order);
					break;
				}
			}
		}
	}
} 	 


/** 
 * Adds the necessary functionality to the Order class for Googla Analytics ecommerce
 * tracking.
 */
class EcommerceGoogleAnaltyicsOrderDecorator extends DataObjectDecorator{
	
	function extraStatics(){
		return array(
			'db' => array(
				'AnalyticsSubmitted' => 'Enum(\'No, Yes, Cancelled\'.\'No\')'
			)
		);
	}
	
	function updateCMSFields($fields){
		// We add the tracking javascript here, so that cancelling an order in the
		// backend gets submitted to Google Analytics immediately
		EcommerceGoogleAnaltyics::submitTrackingData($this->owner);
		
		// Remove the AnalyticsSubmitted field if it's there
		$fields->removeByName('AnalyticsSubmitted');
	}
}