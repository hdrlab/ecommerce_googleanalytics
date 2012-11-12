Ecommerce Google Analytics Tracking
===================================

Developers
-----------------------------------------------
Jeremy Shipman - jeremy [at] burnbright.co.nz (original version)
Hans de Ruiter - hans [at] hdrlab.org.nz (revised version)


Requirements
------------

Silverstripe 2.4+
The Silverstripe Google Analytics module


Setup
-----

- Install both the main Google Analytics module, and this one
- Log into your Google Analytics account, and enable ecommerce for the website whose
ecommerce data you wish to track
- Enable Google Analytics tracking via the Google Analytics module
- You can optionally add a store name to the ecommerce data by adding the following
to your _config.php file:
	EcommerceGoogleAnaltyics::setStoreName('Store Name');


Google documentation links:
---------------------------

http://code.google.com/apis/analytics/docs/tracking/gaTrackingEcommerce.html
http://code.google.com/apis/analytics/docs/gaJS/gaJSApiEcommerce.html


TODO / Room for improvement:
----------------------------

 * Support including shipping amount in the submitted data
 * Support Google Analytics' variation field 
 * Optionally allow recording actions: add to cart, remove, set quantity.
 * Integrate analtyics reporting into CMS for an order. This way site owners an see additional info for the customer.