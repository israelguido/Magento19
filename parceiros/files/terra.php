<?php
/*
 *Gerador de XML específico para o Terra
 */

require_once dirname(dirname(dirname(__FILE__))).'/app/Mage.php';
Mage::app('default');

// Log do tipo catch all que guarda todo erro printado na tela
ob_start();

$inicio_exec = microtime();
error_reporting(E_ALL);
ini_set('display_errors',1);

// Carregando os dados de conexão no arquivo xml do Magento
//$configData = simplexml_load_file('../../app/etc/local.xml');
//$connectData = $configData->global->resources->default_setup->connection;
$connectData = simplexml_load_file('../conf/local.xml');

// Setando as variáveis de conexão com o banco
$host = (string) $connectData->host;
$user = (string) $connectData->username;
$pass = (string) $connectData->password;
$base = (string) $connectData->dbname;

// Conexão com o banco de dados
$connect = mysql_connect($host, $user, $pass, $base) OR die();
mysql_select_db($base, $connect);

// Conexão com o banco de dados
$connect = mysql_connect($host, $user, $pass, $base) OR die();
mysql_select_db($base, $connect);

// Inicia o objeto de XML
$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><HERINGWEBSTORE></HERINGWEBSTORE>");

// Função simples para retorno de queries em formato de array
function exec_query($query) {
  global $connect;
  $result = array();
  $resource = mysql_query($query, $connect);
  while ($row = mysql_fetch_assoc($resource)) {
    $result[] = $row;
  }
  return $result;
}

function addCData($obj, $cdata_text) {
  $node= dom_import_simplexml($obj); 
  $no = $node->ownerDocument; 
  $node->appendChild($no->createCDATASection(utf8_encode($cdata_text)));
} 

// Recupera as informações de urls
$query = "SELECT path, value FROM core_config_data WHERE path IN ('web/unsecure/base_url', 'web/unsecure/base_media_url') ORDER BY path DESC;";

$arrCore = exec_query($query);
foreach ($arrCore AS $val) {
  $path = preg_replace("/web\/unsecure\//", "", $val['path']);
  if ($val['value'] == '{{base_url}}') {
    $value = "http://heringwebstore.lojaemteste.com.br/";
  } else if (preg_match("/{{unsecure_base_url}}/", $val['value'])) {
    $value = preg_replace("/{{unsecure_base_url}}/", $coreData['base_url'], $val['value']);
  } else {
    $value = $val['value'];
  }
  $coreData[$path] = $value;
}

$coreData['base_production_url'] = 'http://www.heringwebstore.com.br/';

// Recupera os dados de parcelamento
$query = "SELECT path, value FROM core_config_data WHERE path IN ('payment/braspag_standard/parcelas_visa', 'payment/braspag_standard/valor_minimo_visa');";
$arrCore = exec_query($query);
foreach ($arrCore AS $val) {
  if (preg_match("/parcelas/", $val['path'])) {
    $coreData['qtd_parcelas'] = (int) $val['value'];
  } else {
    $coreData['vlr_minimo'] = (int) $val['value'];
  }
}

// Recupera as categorias selecionadas
$query = "SELECT category_ids FROM nostress_export WHERE searchengine = 'terra' AND enabled = 1;";
$arrCats = exec_query($query);
$categories = '';
if (is_array($arrCats) && (count($arrCats) > 0 && (isset($arrCats[0]['category_ids']) && (strlen(trim($arrCats[0]['category_ids'])) > 0)))) {
  $categories = trim($arrCats[0]['category_ids']);
}

// Busca dos produtos seguindo os seguintes critérios: configurável, com simples atribuído, com estoque e nas categorias específicas
$query = "SELECT DISTINCT conf.entity_id ";
$query.= "FROM ";
$query.= "  catalog_category_product AS cat INNER JOIN ";
$query.= "  catalog_product_entity AS conf ON (conf.entity_id = cat.product_id".(strlen($categories) > 0 ? " AND cat.category_id IN ($categories)" : "").") INNER JOIN ";
$query.= "  catalog_product_relation AS rel ON (rel.parent_id = conf.entity_id) INNER JOIN ";
$query.= "  cataloginventory_stock_item AS stk ON (stk.product_id = rel.child_id AND qty > 0) ";
$query.= "WHERE ";
$query.= "  conf.type_id = 'configurable';";

$products = exec_query($query);

// Inicia interações nos produtos selecionados para popular o objeto XML;
$count = 0;
foreach ($products AS $product) {
  $count++;
  $product_id = $product['entity_id'];

  // Recupera as informações do produto configurável
  $query = "SELECT ";
  $query.= "  ent.sku, ";
  $query.= "  url.value AS url, ";
  $query.= "  name.value AS nome, ";
  $query.= "  dsc.value AS descricao, ";
  $query.= "  'Hering' AS marca, ";
  $query.= "  ( ";
  $query.= "    SELECT cat.value FROM catalog_category_product AS rel ";
  $query.= "      INNER JOIN catalog_category_entity AS ct ON (ct.entity_id = rel.category_id) ";
  $query.= "      INNER JOIN catalog_category_entity_varchar AS cat ON (cat.entity_id = ct.entity_id AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 3 AND attribute_code = 'name')) ";
  $query.= "    WHERE rel.product_id = ent.entity_id ORDER BY rel.position DESC LIMIT 1 ";
  $query.= "  ) AS categoria, ";
  $query.= "  price.value AS preco_de, ";
  $query.= "  priceto.value AS preco_por, ";
  $query.= "  img.value AS imagem ";
  $query.= "FROM ";
  $query.= "  catalog_product_entity AS ent ";
  $query.= "  LEFT JOIN catalog_product_entity_varchar AS url ON ";
  $query.= "    (";
  $query.= "    url.entity_id = ent.entity_id AND ";
  $query.= "    url.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'url_path') AND ";
  $query.= "    url.store_id = 0";
  $query.= "    ) ";
  $query.= "  LEFT JOIN catalog_product_entity_varchar AS name ON  ";
  $query.= "    ( ";
  $query.= "    name.entity_id = ent.entity_id AND  ";
  $query.= "    name.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'name') AND  ";
  $query.= "    name.store_id = 0 ";
  $query.= "    ) ";
  $query.= "  LEFT JOIN catalog_product_entity_text AS dsc ON  ";
  $query.= "    ( ";
  $query.= "    dsc.entity_id = ent.entity_id AND  ";
  $query.= "    dsc.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'description') AND  ";
  $query.= "    dsc.store_id = 0 ";
  $query.= "    ) ";
  $query.= "  LEFT JOIN catalog_product_entity_varchar AS img ON  ";
  $query.= "    (";
  $query.= "    img.entity_id = ent.entity_id AND ";
  $query.= "    img.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'image') AND ";
  $query.= "    img.store_id = 0";
  $query.= "    ) ";
  $query.= "  LEFT JOIN catalog_product_entity_decimal AS price ON ";
  $query.= "    ( ";
  $query.= "    price.entity_id = ent.entity_id AND ";
  $query.= "    price.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'price') ";
  $query.= "    ) ";
  $query.= "  LEFT JOIN catalog_product_entity_decimal AS priceto ON ";
  $query.= "    ( ";
  $query.= "    priceto.entity_id = ent.entity_id AND ";
  $query.= "    priceto.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'special_price') ";
  $query.= "    ) ";
  $query.= "WHERE ent.entity_id = $product_id;";
  //die($query);
  $entityData = current(exec_query($query));

  // Monta efetivamente o XML
  $prodNode = $xml->addChild('produto');

  addCdata($prodNode->addChild('id_produto'), trim($entityData['sku']));
  addCdata($prodNode->addChild('link_produto'), $coreData['base_production_url'] . trim($entityData['url']) . '?partner=&utm_source=terra&utm_medium=' . trim(urlencode($entityData['nome'])));
  addCdata($prodNode->addChild('titulo'), trim($entityData['nome']));
  addCdata($prodNode->addChild('preco'), number_format($entityData['preco_de'], 2, ',', ''));

  // Calculo de parcelas sem juros
  $preco = ((float) $entityData['preco_por'] > 0 ? (float) $entityData['preco_por'] : (float) $entityData['preco_de']);
  $config_qntd        = (int) Mage::getStoreConfig('payment/braspag/qt_max_parcelas');
  $config_valormin    = (int) Mage::getStoreConfig('payment/braspag/valor_min_parcela');
  $parc = false;
  if (
  	$preco > (float) $config_valormin &&
	(float) $config_valormin > 0 &&
	(floor($preco / (float) $config_valormin) > 1)
  ) {
	$div = floor($preco / (float) $config_valormin);
	$qtd_parcelas = ($config_qntd > $div ? $div : $config_qntd);
	$vlr_parcela = round($preco, 2) / $qtd_parcelas;
	$parc = true;
  }
  
  if($parc) addCdata($prodNode->addChild('parcelamento'), "ou " . $qtd_parcelas . "x de " . number_format($vlr_parcela, 2, ',', ''));
  if( (trim($entityData['preco_por']) != trim($entityData['preco_de'])) && (trim($entityData['preco_por']) > 0) ) addCdata($prodNode->addChild('preco_promocao'), number_format($entityData['preco_por'], 2, ',', ''));
  addCdata($prodNode->addChild('imagem'), $coreData['base_production_url'] . 'media/catalog/product' . trim($entityData['imagem']));
  addCdata($prodNode->addChild('categoria'), trim($entityData['categoria']));
}

// Recuperação de qualquer tipo de output que o script gerou
$buffer = ob_get_clean();

ob_start();
if (strlen($buffer) == 0) {
  $buffer = "[" . date("Y-m-d H:i:s") . "] Script executado com sucesso. gerado XML para $count produtos configuráveis em " . number_format((microtime() - $inicio_exec),3) . "s.\n";
} else {
  $mark = microtime();
  $buffer = "[".date("Y-m-d H:i:s")."] Foram encontrados os seguintes erros ao gerar o script: \nINICIO DO LOG $mark >>\n" . $buffer . "\n>> FIM do log $mark\n)\n"; 
}

// Escreve o output no arquivo de log
//$logdir = dirname(dirname(__FILE__)) . '../../var/log/parceiros';
$logdir = dirname(dirname(__FILE__)) . '/log';
if (!is_dir($logdir)) mkdir($logdir, 0777, true);
$file = fopen($logdir . '/terra.log', 'a+');
fwrite($file, $buffer, strlen($buffer));
fclose($file);
ob_end_clean();

// Printa o arquivo XML
if (!headers_sent()) header('content-type: text/xml; charset=UTF-8');
echo $xml->saveXML();


// Grava o XML gerado no arquivo de integracao
/*
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = false;
$dom->loadXML($xml->asXML());

$filedir = dirname(dirname(dirname(__FILE__))) . '/media/virtualbiz/terra';
if (!is_dir($logdir)) mkdir($logdir, 0777, true);
$dom->save("$filedir/terra.xml");
*/

$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = true;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());

$filedir = '../temp';
if (!is_dir($logdir)) mkdir($logdir, 0777, true);
$dom->save("$filedir/terra_".date("Y-m-d_H-i-s").".xml");
?>
