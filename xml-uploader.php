<?php
require "../classes/connection/connection.php";
require "../classes/newBuildings.php";

$xml = new newBuildings('DB table name');

//парсим xml и каталог новостроек
$xml->getKazanCatalog();
$xml->updateZhkCatalog();
$xml->parseXML('url фида');
//удаляем нерелевантные объекты
$xml->deleteNotRelevant('url фида');
//обновляем цену существующих объектов
$xml->update();
//добавляем объекты, которых нет в нашей бд
$xml->insert('url фида');




