        <?php
        class newBuildings extends connect {
            public $url;
            private $xml;
            private $kazanCatalog = [];
            //данные объекта недвижимости
            private $id;
            private $lat;
            private $lon;
            private $price;
            private $agentName;
            private $agentPhone;
            private $agentLogo;
            private $area;
            private $planImage;
            private $rooms;
            private $floor;
            private $totalFloor;
            private $yandexBuildingId;
            private $yandexHouseId;
            private $zhk;
            private $ochered;
            private $house;
            private $address;
            private $execArr = [];
            private $parsedObjectsId = [];
            private $zhkCatalog = [];
            
            public function getKazanCatalog() {
                //Получаем каталог новостроек
                $yandexCatalogUrl = file_get_contents("https://realty.yandex.ru/newbuildings.tsv");
                $yandexCatalogArray = explode("\n", $yandexCatalogUrl);
                //отсеиваем новостройки из Татарстана
                foreach($yandexCatalogArray as $line ) {
                    list($a, $b, $c, $d, $e, $f, $g, $x, $y) = explode("\t", $line);
                    $linearray = array(
                        "city" => $a,
                        "zhk" => $b,
                        "building-id" => $c,
                        "order" => $d,
                        "built-date" => $e,
                        "address" => $f,
                        "section-id" => $g,
                        "url" => $x,
                        "house" => $y
                    );
                    if($linearray['city'] === "Республика Татарстан") {
                        $this->kazanCatalog[] = $linearray;
                        $this->zhkCatalog[] = $linearray['zhk'];
                    }
                }
            }
            
            
            //Обновляем каталог названий жилых комплексов
            public function updateZhkCatalog() {
                $serverCatalog = array_unique($this->zhkCatalog);
                $getSQL = "SELECT zhk FROM zhkCatalog";
                $query = PDO::prepare($getSQL);
                $query->execute();
                $result = $query->fetchAll();
                $cleanArray = [];
                foreach($result as $key => $value) {
                    $cleanArray[] = $result[$key][0];
                }
                
                $comparison = array_diff($serverCatalog, $cleanArray);
                if(count($comparison) !== 0 ) {
                    $updateSQL = "INSERT INTO zhk(zhk) VALUES (:zhk)";
                    $query = PDO::prepare($updateSQL);
                    foreach($comparison as $value) {
                        $query->execute(array(':zhk' => $comparison[$value])) or die(print_r($query->errorInfo(), true));
                    }
                }

            }
            
            //получить каталог названий жилых комплексов
            public function getZhkCatalog() {
                $getSQL = "SELECT zhk FROM zhkCatalog";
                $query = PDO::prepare($getSQL);
                $query->execute();
                $result = $query->fetchAll(PDO::FETCH_COLUMN, 0);
                $objectArray = [];
                foreach($result as $key => $value) {
                    $objectArray[] = ["value" => $result[$key], "type" => "zhk"];
                }
                print_r(json_encode($objectArray, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
            }
            
            public function parseXML($xmlUrl) {
                $this->xml = simplexml_load_file($xmlUrl);
                foreach($this->xml->offer as $offer) {
                    //set values
                    $this->id = $offer['internal-id'];
                    $this->lat = $offer->location->latitude;
                    $this->lon = $offer->location->longitude;
                    $this->price = $offer->price->value;
                    $this->agentPhone = $offer->{"sales-agent"}->phone;
                    $this->agentName = $offer->{"sales-agent"}->name;
                    $this->agentLogo = $offer->{"sales-agent"}->photo;
                    $this->area = $offer->area->value;
                    $this->planImage = $offer->image;
                    $this->rooms = $offer->rooms;
                    $this->floor = $offer->floor;
                    $this->totalFloor = $offer->{"floors-total"};
                    $this->yandexBuildingId = $offer->{"yandex-building-id"};
                    $this->yandexHouseId = $offer->{"yandex-house-id"};
                    
                    //сравниваем объекты из фида с объектами из каталога недвижимости Казани, и получаем данные из каталога
                    foreach($this->kazanCatalog as $key => $value) {
                        if($this->kazanCatalog[$key]['building-id'] == $this->yandexBuildingId && $this->kazanCatalog[$key]['section-id'] == $this->yandexHouseId) {
                            $this->zhk = $this->kazanCatalog[$key]['zhk'];
                            $this->ochered = $this->kazanCatalog[$key]['order'] . " " . $this->kazanCatalog[$key]['built-date'];
                            $this->house = isset($this->kazanCatalog[$key]['house']) ? str_replace("д. ", "", $this->kazanCatalog[$key]['house']) : null;
                            $this->address = $this->kazanCatalog[$key]['address'];
                        }
                    }
                    
                    $this->execArr[] = [":id" => $this->id, ":zhk" => $this->zhk, ":ochered" => $this->ochered, ":address" => $this->address, ":house" => $this->house, ":lat" => $this->lat, ":lon" => $this->lon, ":agentName" => $this->agentName, ":agentPhone" => $this->agentPhone, ":agentLogo" => $this->agentLogo, ":area" => $this->area, ":planImage" => $this->planImage, ":price" => $this->price, ":rooms" => $this->rooms, ":floor" => $this->floor, ":totalFloor" => $this->totalFloor, ":xmlUrl" => $xmlUrl]; 
                    $this->parsedObjectsId[] = $this->id;
                    
                }
            }
            
            public function deleteNotRelevant($xmlUrl) {
                //получаем id локальных объектов, относящихся к этому фиду
                $getIdSQL = "SELECT xmlId FROM xmlProperties WHERE xmlUrl = :xmlUrl";
                $query = PDO::prepare($getIdSQL);
                $query->execute(array(":xmlUrl" => $xmlUrl));
                $result = $query->fetchAll(PDO::FETCH_COLUMN, 0);
                
                $comparison = array_diff(array_unique($result), $this->parsedObjectsId);
                
                $deleteSQL = "DELETE FROM xmlProperties WHERE xmlId = :id";
                $deleteQuery = PDO::prepare($deleteSQL);
                foreach($comparison as $id => $key) {
                    $deleteQuery->execute(array(":id" => $comparison[$id])) or die(print_r($deleteQuery->errorInfo(), true));
                }
            }
            
            public function update() {
                $updateSQL = "UPDATE xmlProperties SET price = :price, priceChanges = CONCAT(priceChanges, ', ', :price), priceChangeDate = CONCAT(priceChangeDate, ', ', :currentDate) WHERE xmlId = :xmlId";
                $updateQuery = PDO::prepare($updateSQL);
                foreach($this->parsedObjectsId as $key => $value) {
                    $objectId = (string) $this->parsedObjectsId[$key];
                    //получаем цену объекта, записанного в нашей бд
                    $getPriceSQL = "SELECT price FROM xmlProperties WHERE xmlId = :xmlId";
                    $getPriceQuery = PDO::prepare($getPriceSQL);
                    $getPriceQuery->execute(array(":xmlId" => $objectId)) or die(print_r($getPriceQuery->errorInfo(), true));
                    $oldPrice = $getPriceQuery->fetchAll(PDO::FETCH_COLUMN, 0);
                    $currentDate = date('d.m.Y');
                    
                    //Обрабатываем массив объектов, полученный из XML
                    foreach($this->execArr as $object => $var) {
                        if($objectId === (string) $this->execArr[$object][':id']) {
                            $currentPrice = (string) $this->execArr[$object][':price'];
                            //сравниваем цену объекта из фида и цену, записанную в нашей БД
                            if($oldPrice[0] !== $currentPrice) {
                                $updateQuery->execute(array(":price" => intval($currentPrice), ":currentDate" => $currentDate,":xmlId" => $objectId)) or die(print_r($updateQuery->errorInfo(), true));
                            }
                        }
                    }
                }
            }
            
            public function insert($xmlUrl) {
                $existingObjectsSQL = "SELECT xmlId FROM xmlProperties";
                $existingObjectsQuery = PDO::prepare($existingObjectsSQL);
                $existingObjectsQuery->execute() or die(print_r($existingObjectsQuery->errorInfo(), true));
                $existingIds = $existingObjectsQuery->fetchAll(PDO::FETCH_COLUMN, 0);
                
                //проверяем, имеются ли в фиде объекты, несуществующие в нашей базе
                $comparison = array_diff($this->parsedObjectsId, $existingIds);
                
                $currentDate = date('d.m.Y');
                    
                    //заносим в бд недостающие объекты
                    foreach($comparison as $key => $value) {
                        $objectId = (string) $comparison[$key];
                        foreach($this->execArr as $object => $var) {
                            if($objectId === (string) $this->execArr[$object][':id']) {
                                //устанавливаем переменные
                                $id = (string) $this->execArr[$object][':id'];
                                $zhk = (string) $this->execArr[$object][':zhk'];
                                $ochered = (string) $this->execArr[$object][':ochered'];
                                $address = (string) $this->execArr[$object][':address'];
                                $house = (string) $this->execArr[$object][':house'];
                                $lat = (string) $this->execArr[$object][':lat'];
                                $lon = (string) $this->execArr[$object][':lon'];
                                $agentName = (string) $this->execArr[$object][':agentName'];
                                $agentPhone = (string) $this->execArr[$object][':agentPhone'];
                                $agentLogo = (string) $this->execArr[$object][':agentLogo'];
                                $area = (string) $this->execArr[$object][':area'];
                                $planImage = (string) $this->execArr[$object][':planImage'];
                                $price = (string) $this->execArr[$object][':price'];
                                $rooms = (string) $this->execArr[$object][':rooms'];
                                $floor = (string) $this->execArr[$object][':floor'];
                                $totalFloor = (string) $this->execArr[$object][':totalFloor'];
                                   $insertSQL = "INSERT INTO xmlProperties(xmlId, zhkName, ochered, address, house, lat, lon, developerName, developerPhone, developerLogo, totalArea, planImage, price, priceChangeDate, rooms, floor, totalFloors, xmlUrl) 
                                    VALUES 
                                    (:id,
                                     :zhk,
                                     :ochered,
                                     :address,
                                     :house,
                                     :lat,
                                     :lon,
                                     :agentName,
                                     :agentPhone,
                                     :agentLogo,
                                     :area,
                                     :planImage,
                                     :price,
                                     :priceChangeDate,
                                     :rooms,
                                     :floor,
                                     :totalFloor,
                                     :xmlUrl
                                        )";
                                    $insertValues = [":id" => $id, ":zhk" => $zhk, ":ochered" => $ochered, ":address" => $address, ":house" => $house, ":lat" => $lat, ":lon" => $lon, ":agentName" => $agentName, ":agentPhone" => $agentPhone, ":agentLogo" => $agentLogo, ":area" => $area, ":planImage" => $planImage, ":price" => $price, ":priceChangeDate" => $currentDate, ":rooms" => $rooms, ":floor" => $floor, ":totalFloor" => $totalFloor, ":xmlUrl" => $xmlUrl];    
                                    $query = PDO::prepare($insertSQL);  
                                    $query->execute($insertValues) or die(print_r($query->errorInfo(), true));
                            }
                        }

                    }
                }
            }
    }       