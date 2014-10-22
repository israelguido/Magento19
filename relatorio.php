<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);

require_once('app/Mage.php');
umask(0);
Mage::app();

$arquivo = 'relatorio.csv';
$html = <<<HTML
<table>
<tr><th>Numero</th><th>Produto</th><th>Id</th><th>Cor</th><th>Tamanho</th><th>Stock</th></tr>
HTML;

$_rootcatID = Mage::app()->getStore()->getRootCategoryId();

$_testproductCollection = Mage::getResourceModel('catalog/product_collection')
    ->joinField('category_id','catalog/category_product','category_id','product_id=entity_id',null,'left')
    ->joinField('inventory_in_stock','cataloginventory/stock_item','qty','product_id=entity_id')
    ->addAttributeToFilter('category_id', array('in' => array(4,5)))
    ->addAttributeToSelect('color', 'size')
    ->addAttributeToSelect('*');

$_testproductCollection->load()->getSelect()->__toString();

function __getAttList($option) {
    $attributes = Mage::getResourceModel('eav/entity_attribute_collection')
        ->addFieldToFilter('attribute_code', $option)
        ->load(false);
    $attribute = $attributes->getFirstItem();
    $attribute->setSourceModel('eav/entity_attribute_source_table');
    $atts = $attribute->getSource()->getAllOptions(false);
    $result = array();
    foreach($atts as $tmp)
        $result[$tmp['value']] = $tmp['label'];
    return $result;
}


$i = 0;
foreach ($_testproductCollection as $data){
    $i++;

    $name = utf8_decode($data->getName());
    $id = utf8_decode($data->getId());
    $color = utf8_decode($data->getResource()->getAttribute('color')->getFrontend()->getValue($data));
    $size = utf8_decode($data->getResource()->getAttribute('accessories_size')->getFrontend()->getValue($data));
    $qty = number_format($data->getQty(), 0, '.', '');

    $att_size = __getAttList('size');

    if($i % 50) {
        $html_line[] = "
                <tr>
                <td>{$i}</td>
                <td>{$name}</td>
                <td>{$id}</td>
                <td>{$color}</td>
                <td>{$att_size[$data->getSize()]}</td>
                <td>{$qty}</td>
                </tr>";

    }
}
$split = array_chunk($html_line, 50);

foreach($split as  $key => $value){
    $final_doc = $html . implode("\n",$value)."</table>";

    $f = fopen($key.'-'.$arquivo, 'wb');
    fwrite($f , $final_doc );
    fclose($f);

    //file_put_contents( 'media/'.$key.'-'.$arquivo , $final_doc );
}
?>