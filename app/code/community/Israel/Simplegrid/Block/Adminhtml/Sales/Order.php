<?php
class Israel_Simplegrid_Block_Adminhtml_Sales_Order extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'israel_simplegrid';
        $this->_controller = 'adminhtml_sales';
        $this->_headerText = Mage::helper('israel_simplegrid')->__('Order - Israel');
        parent::__construct();
        $this->_removeButton('add');
    }
}