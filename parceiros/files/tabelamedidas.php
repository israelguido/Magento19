<?php
/**
 * TABELA DE MEDIDAS
 * @author	Christopher Silva <christopher.silva@e-smart.com.br> / Carlos Shirasawa <carlos.shirasawa@e-smart.com.br>
 * @since	2012-09-26
 */

//buffering
ob_start();

$inicio_exec = microtime();
error_reporting(E_ALL);
ini_set('display_errors',1); 
 
#chama classe
require_once("../libs/functions.php");

// Carregando os dados de conexão no arquivo xml do Magento
//$configData = simplexml_load_file('../../app/etc/local.xml');
//$connectData = $configData->global->resources->default_setup->connection;
$connectData = simplexml_load_file('../conf/xiris.xml');

// Setando as variáveis de conexão com o banco
$host = (string) $connectData->host;
$user = (string) $connectData->username;
$pass = (string) $connectData->password;
$base = (string) $connectData->dbname;

// Conexão com o banco de dados
$conn = mysql_connect($host, $user, $pass, $base) OR die();
mysql_select_db($base, $conn);

if(!$conn){
	die( 'Could not connect: ' . mysql_error() );
}

// Criando a tabela temporaria
$query= " CREATE TABLE temp_medidas ";
$query.= "( ";
$query.= "  id INT PRIMARY KEY AUTO_INCREMENT ";
$query.= " ,value_id INT ";
$query.= " ,option_id INT ";
$query.= " ,attribute_value_id INT ";
$query.= " ,measure_id INT ";
$query.= " ,cd_index_product_id INT ";
$query.= " ,ds_measure VARCHAR(255) ";
$query.= ") ENGINE=INNODB";

$createtemp = mysql_query($query,$conn);
//die($query);
echo $createtemp;

// select * from template_measures_values_attributes AS t INNER JOIN eav_attribute_option_value AS e ON t.attribute_value_id = e.value_id




