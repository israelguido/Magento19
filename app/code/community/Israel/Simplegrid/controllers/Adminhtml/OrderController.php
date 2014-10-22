<?php
class Israel_Simplegrid_Adminhtml_OrderController extends Mage_Adminhtml_Controller_Action {
    public function indexAction(){
        $this->_title($this->__('Sales'))->_title($this->__('Vai segurando meu titulo'));
        $this->loadLayout();
        $this->_setActiveMenu('sales/sales');
        $this->_addContent($this->getLayout()->createBlock('israel_simplegrid/adminhtml_sales_order'));
        $this->renderLayout();
    }
    public function gridAction(){
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('israel_simplegrid/adminhtml_sales_grid')->toHtml()
        );
    }
    public function exportIsraelExcelAction(){
        $filename = "orders_israel.xml";
        $grid = $this->getLayout()->createBlock('israel_simplegrid/adminhtml_sales_order_grid');
        $this->_prepareDownloadResponse('$filename', $grid->getExcelFile($filename));
    }
}