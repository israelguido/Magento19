<?php
/*
 *Gerador de XML específico para zoom
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

// Inicia o objeto de XML
$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><produtos></produtos>");
// $pData = $xml->addChild('produtos');

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

//Array com id's das categorias que o produto deve estar
$categories = 797;

// Busca dos produtos seguindo os seguintes critérios: configurável, com simples atribuído, com estoque e nas categorias específicas
/*$query = "SELECT DISTINCT conf.entity_id ";
$query.= "FROM ";
$query.= "  catalog_category_product AS cat INNER JOIN ";
$query.= "  catalog_product_entity AS conf ON (conf.entity_id = cat.product_id) INNER JOIN ";
$query.= "  catalog_product_relation AS rel ON (rel.parent_id = conf.entity_id) INNER JOIN ";
$query.= "  cataloginventory_stock_item AS stk ON (stk.product_id = rel.child_id AND qty > 0) ";
$query.= "WHERE ";
$query.= "  cat.category_id = {$categories} AND";
$query.= "  conf.type_id = 'configurable';";
*/

$query = "SELECT conf.entity_id ";
$query.= "  FROM catalog_product_entity AS conf ";
$query.= "  LEFT JOIN catalog_category_product AS cat ON (cat.product_id = conf.entity_id) ";
$query.= "WHERE ";
$query.= "  cat.category_id = {$categories}";
$query.= "  AND conf.type_id = 'configurable';";

$products = exec_query($query);

// Inicia interações nos produtos selecionados para popular o objeto XML;
$count = 0;
foreach ($products AS $product) {
  $count++;
  $product_id = $product['entity_id'];

  // Recupera as informações do produto configurável
  $query = "SELECT ";
  $query.= "  ent.entity_id, ";
  $query.= "  ent.sku, ";
  $query.= "  url.value AS url, ";
  $query.= "  name.value AS nome, ";
  $query.= "  dsc.value AS descricao, ";
  $query.= "  'Hering' AS marca, ";
  $query.= "  price.price AS preco_de, ";
  $query.= "  CASE WHEN ( ";
  $query.= "      (sfr.value < NOW() AND (sto.value > NOW() OR sto.value IS NULL)) OR ";
  $query.= "      (sfr.value IS NULL AND sto.value > NOW()) OR ";
  $query.= "      (sfr.value IS NULL AND sto.value IS NULL) ";
  $query.= "    ) THEN price.final_price ";
  $query.= "    ELSE NULL ";
  $query.= "  END AS preco_por, ";
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
  $query.= "  LEFT JOIN catalog_product_entity_datetime AS sfr ON ";
  $query.= "    ( ";
  $query.= "     sfr.entity_id = ent.entity_id AND ";
  $query.= "     sfr.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'special_from_date') AND ";
  $query.= "     sfr.store_id = 0 ";
  $query.= "     ) ";
  $query.= "  LEFT JOIN catalog_product_entity_datetime AS sto ON ";
  $query.= "    ( ";
  $query.= "    sto.entity_id = ent.entity_id AND ";
  $query.= "    sto.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'special_to_date') AND ";
  $query.= "    sto.store_id = 0 ";
  $query.= "    ) ";
  $query.= "  LEFT JOIN catalog_product_index_price AS price ON ";
  $query.= "    (";
  $query.= "    price.entity_id = ent.entity_id AND ";
  $query.= "    price.customer_group_id = 1";
  $query.= "    ) ";
  $query.= "WHERE ent.entity_id = $product_id;";
//die($query);
  $entityData = current(exec_query($query));

  // Recupera as informações de categorias
  $query = "SELECT cat.value AS categoria ";
  $query.= "FROM catalog_category_product AS rel ";
  $query.= "  INNER JOIN catalog_category_entity AS ct ON (ct.entity_id = rel.category_id) ";
  $query.= "  INNER JOIN catalog_category_entity_varchar AS cat ON (cat.entity_id = ct.entity_id AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 3 AND attribute_code = 'name')) ";
  $query.= "WHERE rel.product_id = $product_id";

  $categories = exec_query($query);

  $cats = array();
  foreach ($categories AS $category) {
    $cats[] = $category['categoria'];
  }
  $strCategoria = implode(' > ', $cats);
  $strCategoria = (isset($cats[0]))?$cats[0]:'';
  $strSubCategoria = (isset($cats[count($cats)-1]))?$cats[count($cats)-1]:'';

  // Recupera as informações relativas aos produtos simples
  $query = "SELECT cor.value AS color, img.value AS image, tam.value AS size ";
  $query.= "FROM ";
  $query.= "  catalog_product_entity_media_gallery AS img ";
  $query.= "  INNER JOIN catalog_product_relation AS rel ON (rel.parent_id = img.entity_id) ";
  $query.= "  INNER JOIN cataloginventory_stock_item AS stk ON (stk.product_id = rel.child_id and qty > 0) ";
  $query.= "  INNER JOIN catalog_product_entity_int AS opt ON ";
  $query.= "    ( ";
  $query.= "    opt.entity_id = rel.child_id AND ";
  $query.= "    opt.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'color') AND ";
  $query.= "    opt.store_id = 0 ";
  $query.= "    ) ";
  $query.= "  INNER JOIN eav_attribute_option_value AS val ON (val.option_id = opt.value AND val.store_id = 0) ";
  $query.= "  INNER JOIN eav_attribute_option_value AS cor ON (cor.option_id = opt.value AND cor.store_id = 1) ";
  $query.= "  INNER JOIN catalog_product_entity_int AS cpi ON ";
  $query.= "    ( ";
  $query.= "    cpi.entity_id = rel.child_id AND ";
  $query.= "    cpi.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'size') AND ";
  $query.= "    cpi.store_id = 0 ";
  $query.= "    ) ";
  $query.= "  INNER JOIN eav_attribute_option_value AS tam ON (tam.option_id = cpi.value AND tam.store_id = 0) ";
  $query.= "WHERE ";
  $query.= "  img.entity_id = $product_id "; 
  $query.= "  AND img.value LIKE CONCAT('%',TRIM(val.value),'%');";

  $multiple = exec_query($query);

  $colors = $images = $sizes = Array();
  foreach ($multiple AS $each) {
    $colors[] = $each['color'];
    $images[] = $each['image'];
    $sizes[] = $each['size'];
  }

  // Monta efetivamente o XML
  $prodNode = $xml->addChild('item');

  //addCdata($prodNode->addChild('sku'), trim($entityData['sku']));
  addCdata( $prodNode->addChild('link'), $coreData['base_url'] . trim($entityData['url']) );
  //addCdata($prodNode->addChild('nome'), trim($entityData['nome']) . ' - HERING');
  addCdata($prodNode->addChild('preco_antigo'), number_format($entityData['preco_de'], 2, ',', ''));
  addCdata($prodNode->addChild('preco'), number_format($entityData['preco_por'], 2, ',', ''));
  //addCdata($prodNode->addChild('imagem'), $coreData['base_media_url'] . 'catalog/product' . trim($entityData['imagem']));

  // Calculo de parcelas sem juros (com base no do terra)
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
  addCdata($prodNode->addChild('numero_parcelas'),$qtd_parcelas);
  addCdata($prodNode->addChild('valor_parcelas'),$vlr_parcela);
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
$logdir = '../log';
if (!is_dir($logdir)) mkdir($logdir, 0777, true);
$file = fopen($logdir . '/blackfriday.log', 'a+');
fwrite($file, $buffer, strlen($buffer));
fclose($file);
ob_end_clean();

// Printa o arquivo XML
if (!headers_sent()) header('content-type: text/xml; charset=UTF-8');
echo $xml->asXML();

