<?php
DataObject::add_extension('Order', 'EcommerceGoogleAnaltyicsOrderDecorator');

Object::add_extension('OrderConfirmationPage_Controller', 'EGAOrderConfirmationPage');
Object::add_extension('AccountPage_Controller', 'EGAAccountPage');