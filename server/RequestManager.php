
<?php
    require './PersistManager.php';

    class RequestManager {
        private static $requestManagerInstance = null;
        private $persistManagerInstance = null;
        private $responseObject = null;
        
        private function __construct(){
            $this->persistManagerInstance = PersistManager::getInstance();
            $this->responseObject = (object) array('data' => null, 'error' => true);
        }
    
        public static function getInstance(){
            if (self::$requestManagerInstance == null) {
                self::$requestManagerInstance = new RequestManager();
            }
            return self::$requestManagerInstance;
        }
        
        private function addToGeoJson($data){
            $response = [];
            foreach ($data as $key => $value) {
                array_push($response, (object) array(
                    "type" => "Feature",
                    "properties" => (object) array(
                        "name" => empty($value['name']) ? null : $value['name'],
                        "surface" => empty($value['surface']) ? null : $value['surface'],
                        "distance" => empty($value['distance']) ? null : $value['distance'],
                        "id" => empty($value['id']) ? null : $value['id'],
                        "length" => empty($value['length']) ? null : $value['length']
                    ),
                    "geometry" => JSON_decode($value['geometry'])
                ));
            }
            return $response;
        }

        public function search($params){
            if($params){
                $this->responseObject->error = false;
                $this->responseObject->data = $this->addToGeoJson($this->persistManagerInstance->query($params));
                return $this->responseObject;
            }
        }

        public function findBarriers($params){
            if($params){
                $this->responseObject->error = false;
                $this->responseObject->data = $this->addToGeoJson($this->persistManagerInstance->queryBarriers($params));
                return $this->responseObject;
            }
        }
    }

    try {
        print_r(json_encode(call_user_func( array( RequestManager::getInstance(), $_POST['action']), $_POST['params'] )));
    } catch (Exception $e) {
        $this->responseObject->error = $e->getMessage();
        print_r(json_encode( $this->responseObject ));
    }
?>