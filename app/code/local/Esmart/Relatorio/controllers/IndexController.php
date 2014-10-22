<?php
class Esmart_Relatorio_IndexController extends Mage_Adminhtml_Controller_Action{
    public function indexAction(){
        $html = <<<HTML
<table>
<tr><th>Produto</th><th>Id</th><th>Cor</th><th>Tamanho</th><th>Stock</th></tr>
HTML;
        $resource = Mage::getModel('core/resource','core_setup');
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->setStoreId(2)
            ->addAttributeToSelect('*');

        $collection->getSelect()
            ->join( array('stock'=>'cataloginventory_stock_item'), 'entity_id = stock.item_id', array('stock.*'));

        foreach ($collection as $data){

            $name = utf8_decode($data->getName());
            $id = utf8_decode($data->getId());
            $color = utf8_decode($data->getResource()->getAttribute('color')->getFrontend()->getValue($data));
            $size = utf8_decode($data->getResource()->getAttribute('accessories_size')->getFrontend()->getValue($data));
            $qty = number_format($data->getQty(), 0, '.', '');

            $html .= <<<HTML
                <tr>
                <td>{$name}</td>
                <td>{$id}</td>
                <td>{$color}</td>
                <td>{$size}</td>
                <td>{$qty}</td>
                </tr>
HTML;

        }

        $html .= "</table>";
        $arquivo = 'relatorio.xls';
        header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header ("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
        header ("Cache-Control: no-cache, must-revalidate");
        header ("Pragma: no-cache");
        header ("Content-type: application/x-msexcel");
        header ("Content-Disposition: attachment; filename=\"{$arquivo}\"" );
        header ("Content-Description: PHP Generated Data for Hering" );
        header ('Content-type: text/html; charset=ISO-8859-1');


        echo $html;
        $fileName   = 'purchasedTicket.xls';
        $content    = $html;
        $this->_prepareDownloadResponse($fileName, $content->getExcelFile());
    }
}