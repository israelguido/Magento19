<?php
// Instancia o Magento
//require(dirname(dirname(__FILE__)).'/app/Mage.php');
//Mage::app();


/**
 * Função simples para retorno de queries em formato de array
 */ 
function exec_query($query) {
  global $connect;
  $result = array();
  $resource = mysql_query($query, $connect);
  while ($row = mysql_fetch_assoc($resource)) {
    $result[] = $row;
  }
  return $result;
}



/**
 * getPaths()
 * 
 * Funcao para retornar os paths de acordo com o ambiente
 * @author	Carlos Shirasawa <carlos.shirasawa@e-smart.com.br>
 * @since	2012-09-26
 * @return	$coreData	Array
 **/
function getPaths($query){
	//executando a query
	$arrCore = exec_query($query);
	
	//array to store data
	$coreData = array();
	foreach ($arrCore AS $val) {
	  $path = preg_replace("/web\/unsecure\//", "", $val['path']);
	  if ($val['value'] == '{{base_url}}') {
	  	//case homologacao
	    $value = "http://heringwebstore.lojaemteste.com.br/";
	  } else if (preg_match("/{{unsecure_base_url}}/", $val['value'])) {
	  	//case http nao seguro
	    $value = preg_replace("/{{unsecure_base_url}}/", $coreData['base_url'], $val['value']);
	  } else {
	  	//case production
	    $value = $val['value'];
	  }
	  $coreData[$path] = $value;
	}
	//returnig data
	return $coreData;
}


function addCData($obj, $cdata_text) {
  $node= dom_import_simplexml($obj); 
  $no = $node->ownerDocument; 
  $node->appendChild($no->createCDATASection(utf8_encode($cdata_text)));
}


/**
 * getInstallments()
 *  Pega nas configuracoes da loja a quantidade de parcelas maxima em uma compra (no caso de compra parcelada)
 * 
 * @author  Christopher Silva <christopher.silva@e-smart.com.br>
 * @return  $data   Array   Retorna array com os campos parcelamento_qntd e parcelamento_valor
 */
function getInstallmentCount( $productPrice )
{
    $config_qntd        = (int) Mage::getStoreConfig('payment/braspag_standard/parcelas_visa');
    $config_valormin    = (int) Mage::getStoreConfig('payment/braspag_standard/valor_minimo_visa');
    
    $price = (float) number_format( $productPrice, 2, '.','');  
    
    if( $price < (2*$config_valormin) ) {
        $data = array(
             'parcelamento_qntd'    => 1
            ,'parcelamento_valor'   => $price
        );

        return $data;
    } else {
        $parcelamento_qntd = floor($price / $config_valormin);
        $parcelamento_valor = number_format($price/$parcelamento_qntd,2,'.','');
        
        $data = array(
             'parcelamento_qntd'    => $parcelamento_qntd
            ,'parcelamento_valor'   => $parcelamento_valor
        );
        
        return $data;
    }
}