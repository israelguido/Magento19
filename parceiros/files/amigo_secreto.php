<?php
/**
 * Gera XML de produtos que estao na categoria especificada
 * @author  Christopher Silva <christopher.silva@e-smart.com.br>
 * @since   2012-10-19
 */

//buffering
ob_start();

$inicio_exec = microtime();
error_reporting(E_ALL);
ini_set('display_errors',1); 
 
#chama classe
require_once("../libs/functions.php");

// Carregando os dados de conexão no arquivo xml do Magento
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
$pData = $xml->addChild('produtos');

//pegando os paths
$query = "SELECT path, value FROM core_config_data WHERE path IN ('web/unsecure/base_url', 'web/unsecure/base_media_url') ORDER BY path DESC;";
$paths = getPaths($query);
//$coreData['prod_url'] = "http://www.heringwebstore.com.br/media/"; ### Remover este índice e suas referências

// Busca dos produtos seguindo os seguintes critérios: configuravel e com simples atribuído
$query = "SELECT DISTINCT conf.entity_id ";
$query.= "FROM ";
$query.= "catalog_product_entity AS conf INNER JOIN ";
$query.= "catalog_product_relation AS rel ON (rel.parent_id = conf.entity_id) ";
$query.= "WHERE ";
$query.= "  conf.type_id = 'configurable';";
$products = exec_query($query);

// Inicia interações nos produtos selecionados para popular o objeto XML;
$productData = array();
$count = 0;
$count_simples = 0;
foreach( $products as $product ) {
    $product_id = $product['entity_id'];
    $count++;
    
    // Recupera as informações do produto configurável
    $query = "SELECT ";
    $query.= "  ent.sku, ";
    $query.= "  url.value AS url, ";
    $query.= "  name.value AS nome, ";
    $query.= "  dsc.value AS descricao, ";
    $query.= "  'Hering' AS marca, ";
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
    $query = "SELECT cat.value AS categoria, ";
    $query.= "(select value from catalog_category_entity_varchar where attribute_id = 33 AND entity_id = ct.parent_id) as categpai ";
    $query.= "FROM catalog_category_product AS rel ";
    $query.= "  INNER JOIN catalog_category_entity AS ct ON (ct.entity_id = rel.category_id) ";
    $query.= "  INNER JOIN catalog_category_entity_varchar AS cat ON (cat.entity_id = ct.entity_id AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 3 AND attribute_code = 'name')) ";
    $query.= "WHERE rel.product_id = $product_id";
    $categories = exec_query($query);

    $cats = array();
    $parentsCategoria = array();
    foreach ($categories AS $category) {
        if($category['categoria'] != 'Amigo Secreto'){
            $cats[] = $category['categoria'];
            $parentsCategoria[] = $category['categpai'];
        }
    }

    $strCategoria = implode(',', $cats);
    //$strCategoriaPai = implode(',', $parents);

    // Recupera as informações relativas aos produtos simples    
    $query = "SELECT cor.value AS color, cor.option_id AS colorid, img.value AS image, tam.value AS size, tam.option_id AS sizeid, simple.sku AS sku, simple.entity_id AS simpleid, ";
    $query.= " stk.qty AS stock, ";
    $query.= " cat.category_id AS catid, ";
    $query.= " rgb.desc_rgb AS color_rgb ";
    $query.= "FROM ";
    $query.= "  catalog_product_entity_media_gallery AS img ";
    $query.= "  INNER JOIN catalog_product_relation AS rel ON (rel.parent_id = img.entity_id) ";
    $query.= "  INNER JOIN cataloginventory_stock_item AS stk ON (stk.product_id = rel.child_id) ";
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
    $query.= "  INNER JOIN catalog_product_entity AS simple ON ";
    $query.= "    ( ";
    $query.= "    simple.entity_id = rel.child_id ";
    $query.= "    ) ";
    $query.= "  INNER JOIN hering_rgb AS rgb ON (simple.entity_id = rgb.cod_mage_produto) ";
    $query.= "  INNER JOIN catalog_category_product AS cat ON (img.entity_id = cat.product_id AND cat.category_id = 577) ";
    $query.= "WHERE ";
    $query.= "  img.entity_id = $product_id "; 
    $query.= "  AND img.value LIKE CONCAT('%',TRIM(val.value),'%') ";
    $query.= " group by simple.entity_id;";

    $multiple = exec_query($query);

    //verifica se existe resultado para o produto simples, caso positivo monta o xml
    if ( is_array($multiple) && (count($multiple) > 0) ) {
        // Monta efetivamente o XML
        $prodNode = $pData->addChild('produto');
        
        addCdata($prodNode->addChild('entity_id'), trim($product_id));
        addCdata($prodNode->addChild('sku'), trim($entityData['sku']));
        addCdata($prodNode->addChild('link'), $paths['base_url'] . trim($entityData['url']) . '?partner=amigosecreto');
        addCdata($prodNode->addChild('nome'), trim($entityData['nome']));
        addCdata($prodNode->addChild('marca'), trim($entityData['marca']));
        addCdata($prodNode->addChild('categoria'), trim($parentsCategoria[0]));
        addCdata($prodNode->addChild('categoria-sub'), trim($strCategoria));
        addCdata($prodNode->addChild('descricao'), trim($entityData['descricao']));
        addCdata($prodNode->addChild('preco_de'), number_format($entityData['preco_de'], 2, ',', ''));
        if(trim($entityData['preco_por']) != trim($entityData['preco_de']) && ((int) $entityData['preco_por'] > 0)) addCdata($prodNode->addChild('preco_por'), number_format($entityData['preco_por'], 2, ',', ''));
        addCdata($prodNode->addChild('imagem'), $paths['base_url'] . 'media/catalog/product' . trim($entityData['imagem']));
        $itensNode = $prodNode->addChild('itens');

        foreach ($multiple AS $each) {
            $count_simples++;
            $itemNode = $itensNode->addChild('item');
            $params = array();
            $params[] = "partner=amigo_secreto";
            $params[] = "product=" . trim($product_id);
            $params[] = "super_attribute[1019]=" . trim($each['sizeid']);
            $params[] = "super_attribute[80]=" . trim($each['colorid']);
            $params[] = "qty=";
            $url = $paths['base_url'] . "checkout/cart/add?" . implode("&", $params);
            addCdata($itemNode->addChild('sku'), trim($each['simpleid']));
            addCdata($itemNode->addChild('quantidade'), trim($each['stock']));
            //addCdata($itemNode->addChild('cor'), trim($each['color']));
            addCdata($itemNode->addChild('cor'), trim($each['color_rgb']));
            ######### addCdata($itemNode->addChild('imagem'), $coreData['base_media_url'] . 'catalog/product' . trim($each['image']));
            addCdata($itemNode->addChild('imagem'), $paths['base_url'] . 'media/catalog/product' . trim($each['image']));
            addCdata($itemNode->addChild('tamanho'), trim($each['size']));
            addCdata($itemNode->addChild('addurl'), trim($url));
        }
    }//end if
}//end foreach


// Recuperação de qualquer tipo de output que o script gerou
$buffer = ob_get_clean();

ob_start();
if (strlen($buffer) == 0) {
  $buffer = "[" . date("Y-m-d H:i:s") . "] Script executado com sucesso. Gerado XML para $count produtos configuráveis e $count_simples produtos simples em " . number_format((microtime() - $inicio_exec),3) . "s.\n";
} else {
  $mark = microtime();
  $buffer = "[".date("Y-m-d H:i:s")."] Foram encontrados os seguintes erros ao gerar o script: \nINICIO DO LOG $mark >>\n" . $buffer . "\n>> FIM do log $mark\n)\n"; 
}

// Escreve o output no arquivo de log
//$logdir = dirname(dirname(__FILE__)) . '../../var/log/parceiros';
$logdir = '../log/';
if (!is_dir($logdir)) mkdir($logdir, 0777, true);
$file = fopen($logdir . 'amigo_secreto.new.log', 'a+');
fwrite($file, $buffer, strlen($buffer));
fclose($file);
ob_end_clean();

// Printa o arquivo XML
if (!headers_sent()) header('content-type: text/xml; charset=UTF-8');
echo $xml->asXML();





