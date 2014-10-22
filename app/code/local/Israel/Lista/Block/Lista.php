<?php
class Israel_Lista_Block_Lista extends Mage_Catalog_Block_Product_List {
    public function getToolbarHtml(){
        return $this->getChildHtml('toolbar');
    }
}