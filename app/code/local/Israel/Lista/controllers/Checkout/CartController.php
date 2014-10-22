<?php
require_once('Mage/Checkout/controllers/CartController.php');

class Israel_Lista_Checkout_CartController extends Mage_Checkout_CartController{
    public function addAction(){
        //echo __FILE__; die;
        //error_log('Yes, I did it!');
        parent::addAction();
    }
}
