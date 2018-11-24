<?php
class PersistManager {
    private static $persistManagerInstance = null;
    private $connection = null;
    private $query = null;
    private $params = null;
    
    private function __construct(){
        try {
            $this->connection = pg_connect("host=localhost port=5432 dbname=pdt_project_slovakia user=postgres password=postgres");
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    function __destruct() {
        if($this->connection){
            pg_close($this->connection);
        }
    }
   
    public static function getInstance(){
      if (self::$persistManagerInstance == null) {
        try {
            self::$persistManagerInstance = new PersistManager();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
      }
      return self::$persistManagerInstance;
    }

    private function getSurfaceCondition($params, $params_counter, $alias = 'A'){
        $surfaces = '';
        $counter = $params_counter;
        foreach ($params['surfaceClausule'] as $key => $value) {
            if ($value != 'NULL'){
                $surfaces .= " ".$alias.".surface = $".$counter.((count($params['surfaceClausule']) - 1) != $key ? ' OR' : '');
            } else {
                $surfaces .= " ".$alias.".surface IS NULL".((count($params['surfaceClausule']) - 1) != $key ? ' OR' : '');
            }
            $counter += 1;
        }
        return $surfaces;
    }

    private function getRangeCondition($params, $params_counter){
        return "ST_DWithin(ST_Transform(A.way, 4326), ST_SetSRID(ST_MakePoint($1, $2)::geography, 4326), $".$params_counter.")";
    }

    private function getJoinWater($alias = 'A'){
        return " JOIN planet_osm_line AS water ON ST_DWithin(".$alias.".way, water.way, 100)";
    }

    private function constructBasicScenario($params){
        $query_params = array($params['myPosition'][1], $params['myPosition'][0]);
        $query = "SELECT ST_AsGeoJSON(ST_Transform(A.way, 4326)) AS geometry, 
                A.surface,
                A.name,
                ST_Distance(ST_Transform(A.way, 4326), ST_SetSRID(ST_MakePoint($1, $2)::geography, 4326)) AS distance,
                A.osm_id AS id,
                ST_Length(A.way) AS length
                FROM planet_osm_line AS A
                ".($params['water'] == 'true' ? $this->getJoinWater() : '')."
                WHERE A.highway = 'cycleway'
                ".($params['water'] == 'true' ? 'AND water.waterway IS NOT NULL' : '')."
                ".(empty($params['surfaceClausule']) ? "" : "AND (".$this->getSurfaceCondition($params, (count($query_params) + 1)).")");
        // Remove NULL FROM PARAMS
        if (!empty($params['surfaceClausule'])) {
            if (($key = array_search('NULL', $params['surfaceClausule'])) !== false){
                unset($params['surfaceClausule'][$key]);
            }
            $query_params = array_merge($query_params, $params['surfaceClausule']);
        }
        // Range distance
        if (!empty($params['rangeDistance'])){
            $query .= " AND ".$this->getRangeCondition($params, (count($query_params) + 1));
            array_push($query_params, $params['rangeDistance']);
        }
        $query .= ' ORDER BY distance';
        // Resluts limit
        if (!empty($params['limit'])){
            $query .= " LIMIT $".(count($query_params) + 1);
            array_push($query_params, $params['limit']);
        }
        return array($query, $query_params);
    }

    private function constructLongestRoadScenario($params){
        $query_params = array($params['myPosition'][1], $params['myPosition'][0]);
        $query = "WITH longest AS (
            SELECT A.osm_id, SUM(ST_Length(B.way)) AS length FROM planet_osm_line AS A
            JOIN planet_osm_line AS B ON ST_Intersects(A.way, B.way)
            ".($params['water'] == 'true' ? $this->getJoinWater() : '')."
            WHERE A.highway = 'cycleway' AND B.highway = 'cycleway'
            ".($params['water'] == 'true' ? 'AND water.waterway IS NOT NULL' : '')."
            ".(empty($params['surfaceClausule']) ? "" : "AND (".$this->getSurfaceCondition($params, (count($query_params) + 1)).")");
        // Remove NULL FROM PARAMS
        if (!empty($params['surfaceClausule'])) {
            if (($key = array_search('NULL', $params['surfaceClausule'])) !== false){
                unset($params['surfaceClausule'][$key]);
            }
            $query_params = array_merge($query_params, $params['surfaceClausule']);
        }
        // Range distance
        if (!empty($params['rangeDistance'])){
            $query .= " AND ".$this->getRangeCondition($params, (count($query_params) + 1));
            array_push($query_params, $params['rangeDistance']);
        }
        $query .= " GROUP BY A.osm_id
                    ORDER BY length DESC";
        // Resluts limit
        if (!empty($params['limit'])){
            $query .= " LIMIT $".(count($query_params) + 1);
            array_push($query_params, $params['limit']);
        }
        $query .= ')';
        $query .= "SELECT ST_AsGeoJSON(ST_Transform(C.way, 4326)) AS geometry,
                        C.surface,
                        C.name,
                        ST_Distance(ST_Transform(C.way, 4326), ST_SetSRID(ST_MakePoint($1, $2)::geography, 4326)) AS distance,
                        C.osm_id AS id, 
                        ST_Length(C.way) AS length,
                        C.way,
                        C.highway
                        FROM planet_osm_line AS A
                    JOIN longest AS B ON B.osm_id = A.osm_id
                    JOIN planet_osm_line AS C ON ST_Intersects(A.way, C.way)
                    WHERE C.highway = 'cycleway'
                    ORDER BY distance";
        return array($query, $query_params);
    }

    private function constructBasicBarierScenario($params){
        $query_params = array($params['myPosition'][1], $params['myPosition'][0]);
        $query = "SELECT ST_AsGeoJSON(ST_Transform(A.way, 4326)) AS geometry, 
                    A.barrier AS name,
                    ST_Distance(ST_Transform(A.way, 4326), ST_SetSRID(ST_MakePoint($1, $2)::geography, 4326)) AS distance
                    FROM planet_osm_point AS A
                    JOIN planet_osm_line AS B ON (ST_Contains(B.way, A.way) OR ST_Touches(A.way, B.way))
                    ".($params['water'] == 'true' ? $this->getJoinWater('B') : '')."
                    WHERE A.barrier IS NOT NULL AND B.highway = 'cycleway'
                    ".($params['water'] == 'true' ? 'AND water.waterway IS NOT NULL' : '')."
                    ".(empty($params['surfaceClausule']) ? "" : "AND (".$this->getSurfaceCondition($params, (count($query_params) + 1), 'B').")");
        // Remove NULL FROM PARAMS
        if (!empty($params['surfaceClausule'])) {
            if (($key = array_search('NULL', $params['surfaceClausule'])) !== false){
                unset($params['surfaceClausule'][$key]);
            }
            $query_params = array_merge($query_params, $params['surfaceClausule']);
        }
        // Range distance
        if (!empty($params['rangeDistance'])){
            $query .= " AND ".$this->getRangeCondition($params, (count($query_params) + 1));
            array_push($query_params, $params['rangeDistance']);
        }
        return array($query, $query_params);
    }

    private function constructLongestRoadBarierScenario($params){
        $query_params = array($params['myPosition'][1], $params['myPosition'][0]);
        $query = "WITH longest AS (
            SELECT A.osm_id, SUM(ST_Length(B.way)) AS length FROM planet_osm_line AS A
            JOIN planet_osm_line AS B ON ST_Intersects(A.way, B.way)
            ".($params['water'] == 'true' ? $this->getJoinWater() : '')."
            WHERE A.highway = 'cycleway' AND B.highway = 'cycleway'
            ".($params['water'] == 'true' ? 'AND water.waterway IS NOT NULL' : '')."
            ".(empty($params['surfaceClausule']) ? "" : "AND (".$this->getSurfaceCondition($params, (count($query_params) + 1)).")");
        // Remove NULL FROM PARAMS
        if (!empty($params['surfaceClausule'])) {
            if (($key = array_search('NULL', $params['surfaceClausule'])) !== false){
                unset($params['surfaceClausule'][$key]);
            }
            $query_params = array_merge($query_params, $params['surfaceClausule']);
        }
        // Range distance
        if (!empty($params['rangeDistance'])){
            $query .= " AND ".$this->getRangeCondition($params, (count($query_params) + 1));
            array_push($query_params, $params['rangeDistance']);
        }
        $query .= " GROUP BY A.osm_id
                    ORDER BY length DESC";
        // Resluts limit
        if (!empty($params['limit'])){
            $query .= " LIMIT $".(count($query_params) + 1);
            array_push($query_params, $params['limit']);
        }
        $query .= '), longestIntersect AS (';
        $query .= "SELECT ST_AsGeoJSON(ST_Transform(C.way, 4326)) AS geometry,
                        C.surface,
                        C.name,
                        ST_Distance(ST_Transform(C.way, 4326), ST_SetSRID(ST_MakePoint($1, $2)::geography, 4326)) AS distance,
                        C.osm_id AS id, 
                        ST_Length(C.way) AS length,
                        C.way,
                        C.highway
                        FROM planet_osm_line AS A
                    JOIN longest AS B ON B.osm_id = A.osm_id
                    JOIN planet_osm_line AS C ON ST_Intersects(A.way, C.way)
                    WHERE C.highway = 'cycleway'
                    ORDER BY distance) ";
        $query .= "SELECT ST_AsGeoJSON(ST_Transform(A.way, 4326)) AS geometry, 
                A.barrier AS name,
                ST_Distance(ST_Transform(A.way, 4326), ST_SetSRID(ST_MakePoint($1, $2)::geography, 4326)) AS distance
                FROM planet_osm_point AS A
                JOIN longestIntersect AS B ON (ST_Contains(B.way, A.way) OR ST_Touches(A.way, B.way))
                ".($params['water'] == 'true' ? $this->getJoinWater('B') : '')."
                WHERE A.barrier IS NOT NULL AND B.highway = 'cycleway'";
        return array($query, $query_params);
    }

    public function queryBarriers($params){
        try {
            $response = array();
            if($params['longest'] == 'true'){
                $res = $this->constructLongestRoadBarierScenario($params);
            } else {
                $res = $this->constructBasicBarierScenario($params);
            }
            $this->query = $res[0];
            $this->params = $res[1];
            $result = pg_query_params($this->connection, $this->query, $this->params);
            while($rs = pg_fetch_assoc($result)){
                array_push($response, $rs);
            }
            return $response;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function query($params){
        try {
            $response = array();
            if($params['longest'] == 'true'){
                $res = $this->constructLongestRoadScenario($params);
            } else {
                $res = $this->constructBasicScenario($params);
            }
            $this->query = $res[0];
            $this->params = $res[1];
            $result = pg_query_params($this->connection, $this->query, $this->params);
            while($rs = pg_fetch_assoc($result)){
                array_push($response, $rs);
            }
            return $response;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
  }
?>