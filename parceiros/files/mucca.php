<?php
/*
 *Gerador de XML específico para o Mucca
 */

const COR = 0;
const TAMANHO = 1;

// Log do tipo catch all que guarda todo erro printado na tela
ob_start();

$inicio_exec = microtime();
error_reporting(E_ALL);
ini_set('display_errors',1);

/*
// Carregando os dados de conexão no arquivo xml do Magento
//$configData = simplexml_load_file('../../app/etc/local.xml');
//$connectData = $configData->global->resources->default_setup->connection;
$connectData = simplexml_load_file('/store/hering/public_html/trunk/app/etc/local.xml');
$connection = $connectData->global->resources->default_setup->connection;

// Setando as variáveis de conexão com o banco
$host = (string) $connection->host;
$user = (string) $connection->username;
$pass = (string) $connection->password;
$base = (string) $connection->dbname;
*/

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
$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><HERINGWEBSTORE></HERINGWEBSTORE>");

// Função simples para retorno de queries em formato de array
function exec_query($query) {
  global $connect;
  $result = array();
  $resource = mysql_query($query);

  if (!$resource)
  {
  echo ' Invalid MySQL Query: ' . mysql_error () . "\n";
  var_dump ($query);
  die;
  }
  
  while ($row = mysql_fetch_assoc($resource)) $result[] = $row;
  
  return $result;
}

function getAttributeOptionValue ($option_id)
{
    if (empty($option_id)) return;
    $sql = "SELECT value FROM eav_attribute_option_value where option_id = $option_id AND store_id = 1";
    //$children = Mage::getSingleton ('core/resource')->getConnection ('core_read')->query ($sql)->fetchAll (PDO::FETCH_ASSOC);
    $children = exec_query ($sql);
    return $children [0]['value'];
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
$query = "SELECT category_ids FROM nostress_export WHERE searchengine = 'mucca' AND enabled = 1;";
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
  $query.= "  ent.entity_id, ";
  $query.= "  ent.sku, ";
  $query.= "  url.value AS url, ";
  $query.= "  name.value AS nome, ";
  $query.= "  dsc.value AS descricao, ";
  $query.= "  sex.value AS sexo, ";
  $query.= "  'Hering' AS marca, ";
  $query.= "  ( ";
  $query.= "    SELECT url.value FROM catalog_category_product AS rel ";
  $query.= "      INNER JOIN catalog_category_entity AS ct ON (ct.entity_id = rel.category_id) ";
  $query.= "      INNER JOIN catalog_category_entity_varchar AS url ON (url.entity_id = ct.entity_id AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 3 AND attribute_code = 'url_path')) ";
  $query.= "    WHERE rel.product_id = ent.entity_id ORDER BY rel.position DESC LIMIT 1 ";
  $query.= "  ) AS cat_url, ";
  $query.= "  ( ";
  $query.= "    SELECT cat.value FROM catalog_category_product AS rel ";
  $query.= "      INNER JOIN catalog_category_entity AS ct ON (ct.entity_id = rel.category_id) ";
  $query.= "      INNER JOIN catalog_category_entity_varchar AS cat ON (cat.entity_id = ct.entity_id AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 3 AND attribute_code = 'name')) ";
  $query.= "    WHERE rel.product_id = ent.entity_id ORDER BY rel.position DESC LIMIT 1 ";
  $query.= "  ) AS categoria, ";
  $query.= "  price.value AS preco_de, ";
  $query.= "  priceto.value AS preco_porr, ";
  $query.= "  CASE WHEN ( ";
  $query.= "      (sfr.value <= NOW() AND (sto.value >= NOW() OR sto.value IS NULL)) OR ";
  $query.= "      (sfr.value IS NULL AND sto.value >= NOW()) OR ";
  $query.= "      (sfr.value IS NULL AND sto.value IS NULL) ";
  $query.= "    ) THEN priceto.value ";
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
  $query.= "  LEFT JOIN catalog_product_entity_text AS sex ON  ";
  $query.= "    ( ";
  $query.= "    sex.entity_id = ent.entity_id AND  ";
  $query.= "    sex.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'sex') AND  ";
  $query.= "    sex.store_id = 0 ";
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
  
  $strCategoria = implode(', ', $cats);
  

  /**
   * Monta efetivamente o XML
   **/ 
  $prodNode = $xml->addChild('produto');

  // ### addCdata($prodNode->addChild('id_produto'), trim($entityData['sku']));
  addCdata($prodNode->addChild('link'), $coreData['base_production_url'] . (strlen(trim($entityData['cat_url'])) > 0 ? trim($entityData['cat_url']).'/' : '') . trim($entityData['url']) . '?partner=&utm_source=mucca&utm_medium=' . trim(urlencode($entityData['nome'])));
  addCdata($prodNode->addChild('imagem'), $coreData['base_production_url'] . 'media/catalog/product' . trim($entityData['imagem']));
  addCdata($prodNode->addChild('nome'), trim($entityData['nome']));
  addCdata($prodNode->addChild('categoria'), trim($strCategoria));
  addCdata($prodNode->addChild('descricao'), trim($entityData['descricao']));
  
  $preco = (float) $entityData ['preco_de'];
  $preco_promo = ((float) $entityData ['preco_por'] > 0 ? (float) $entityData ['preco_por'] : null);
  $preco_promo_antigo = ((float) $entityData ['preco_porr'] > 0 ? (float) $entityData ['preco_porr'] : null);
  
  addCdata ($prodNode->addChild ('valor'), number_format ($preco, 2, ',', ''));
  if($preco_promo != null)
    addCdata ($prodNode->addChild ('valor_promo'), number_format ($preco_promo, 2, ',', ''));

  //addCdata ($prodNode->addChild ('valor_promo_antigo'), number_format ($preco_promo_antigo, 2, ',', ''));
  
  addCdata ($prodNode->addChild ('marca'), 'Hering');
  addCdata ($prodNode->addChild ('modelo'), '');
  addCdata ($prodNode->addChild ('sexo'), getAttributeOptionValue ($entityData ['sexo']));

  // Recupera as informações relativas aos produtos simples
  $query = "SELECT cor.value AS color, img.value AS image, tam.value AS size";
  $query.= " FROM ";
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
    $colors[] = trim($each['color']);
    $images[] = trim($each['image']);
    $sizes[] = trim($each['size']);
  }
  
  if( (is_array($colors)) && (count($colors) > 0) ) {
    $strColors = implode( ', ', array_unique($colors) );
    addCdata($prodNode->addChild('cor'), trim($strColors));
  }

  if (is_array($sizes) && (count($sizes) > 0)) {
    $strSizes = implode( ', ', array_unique($sizes) );
    addCdata($prodNode->addChild('tamanho'), trim($strSizes));
  }
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
$file = fopen($logdir . '/mucca.log', 'a+');
fwrite($file, $buffer, strlen($buffer));
fclose($file);
ob_end_clean();

// Printa o arquivo XML
if (!headers_sent()) header('content-type: text/xml; charset=UTF-8');
echo $xml->asXML();

/*
// Grava o XML gerado no arquivo de integracao
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = false;
$dom->loadXML($xml->asXML());

$filedir = dirname(dirname(dirname(__FILE__))) . '/media/virtualbiz/mucca';
if (!is_dir($logdir)) mkdir($logdir, 0777, true);
$dom->save("$filedir/mucca.xml");
*




$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = true;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());

$filedir = '../temp';
if (!is_dir($logdir)) mkdir($logdir,0777, true);
$dom->save("$filedir/mucca_".date("Y-m-d_H-i-s").".xml");*/
?>
