<?php
/**
 *
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 *
 * @copyright 2005 Camptocamp SA
 * @package Tests
 * @version $Id$
 */

/**
 * Abstract test case
 */
require_once 'PHPUnit/Framework/TestCase.php';
require_once('client/CartoserverServiceWrapper.php');

require_once(CARTOWEB_HOME . 'coreplugins/query/common/Query.php');
require_once(CARTOWEB_HOME . 'coreplugins/layers/common/Layers.php');
require_once(CARTOWEB_HOME . 'common/BasicTypes.php');

/**
 * Unit test for server query plugin via webservice. 
 * @package Tests
 * @author Yves Bolognini <yves.bolognini@camptocamp.com>
 */
class projects_testMain_coreplugins_query_server_RemoteServerQueryTest
                    extends client_CartoserverServiceWrapper {

    public function isTestDirect() {
        return true;   
    }

    protected function getMapId() {
        return 'test_main.test';
    }

    /**    
     * Returns a {@link MapRequest} for a query on a point type layers
     * with a point like query
     * @return MapRequest
     */
    private function getMapPointRequestAllLayers() {

        $queryRequest = new QueryRequest();
        $point = new Point();
        $point->setXY(-0.5285, 51.7589);
        $queryRequest->shape = $point;
        $queryRequest->defaultTableFlags = new TableFlags();
        $queryRequest->defaultTableFlags->returnAttributes = true;
        $queryRequest->defaultTableFlags->returnTable = true;
        $queryRequest->queryAllLayers = true;

        $bboxRequest = new BboxLocationRequest();
        $bbox = new Bbox();
        $bbox->setFromBbox(-0.67,51.64,-0.39,51.85);
        $bboxRequest->bbox = $bbox;
        $locationRequest = new LocationRequest();
        $locationRequest->locationType = LocationRequest::LOC_REQ_BBOX;
        $locationRequest->bboxLocationRequest = $bboxRequest; 
        
        $mapRequest = $this->createRequest();
        $mapRequest->locationRequest = $locationRequest;
        $mapRequest->queryRequest = $queryRequest;        
        $mapRequest->layersRequest = new LayersRequest();
        $mapRequest->layersRequest->layerIds = array('more_points');
        
        return $mapRequest;
    }

    /**   
     * Returns a {@link MapRequest} for a query on all selected layers
     * with a rubber band (bbox) like query
     * @return MapRequest
     */
    private function getMapBboxRequestAllLayers() {
    
        $queryRequest = new QueryRequest();
        $bbox = new Bbox();
        $bbox->setFromBbox(-0.75, 51, 0.75, 51.5);
        $queryRequest->shape = $bbox;
        $queryRequest->defaultTableFlags = new TableFlags();
        $queryRequest->defaultTableFlags->returnAttributes = true;
        $queryRequest->defaultTableFlags->returnTable = true;
        $queryRequest->queryAllLayers = true;

        $mapRequest = $this->createRequest();
        $mapRequest->queryRequest = $queryRequest;        
        $mapRequest->layersRequest = new LayersRequest();
        $mapRequest->layersRequest->layerIds = 
                    array('POLYGON1', 'line', 'point');
        
        return $mapRequest;
    }
    
    /**    
     * Returns a {@link MapRequest} for a query on all selected layers
     * with a polygon like query
     * @return MapRequest
     */
    private function getMapPolygonRequestAllLayers() {
    
        $queryRequest = new QueryRequest();
        $point1 = new Point();
        $point1->setXY(-0.75, 51);
        $point2 = new Point();
        $point2->setXY(-0.75, 51.5);
        $point3 = new Point();
        $point3->setXY(0.80, 52);
        $point4 = new Point();
        $point4->setXY(0.75, 51);
        $point5 = new Point();
        $point5->setXY(-0.75, 51);
        $polygon = new Polygon();
        $polygon->points = array($point1, $point2, $point3, $point4, $point5);
        $queryRequest->shape = $polygon;
        $queryRequest->defaultTableFlags = new TableFlags();
        $queryRequest->defaultTableFlags->returnAttributes = true;
        $queryRequest->defaultTableFlags->returnTable = true;
        $queryRequest->queryAllLayers = true;

        $mapRequest = $this->createRequest();
        $mapRequest->queryRequest = $queryRequest;        
        $mapRequest->layersRequest = new LayersRequest();
        $mapRequest->layersRequest->layerIds = 
                    array('POLYGON1', 'line', 'point');
        
        return $mapRequest;
    }
    
    /**   
     * Returns a {@link MapRequest} for a query on all selected layers
     * with a circle like query
     * @return MapRequest
     */
    private function getMapCircleRequestAllLayers() {
    
        $circle = new Circle();
        $circle->x = 0;
        $circle->y = 51.48;
        $circle->radius = 0.73;
        $queryRequest = new QueryRequest();
        $queryRequest->shape = $circle;
        $queryRequest->defaultTableFlags = new TableFlags();
        $queryRequest->defaultTableFlags->returnAttributes = true;
        $queryRequest->defaultTableFlags->returnTable = true;
        $queryRequest->queryAllLayers = true;

        $mapRequest = $this->createRequest();
        $mapRequest->queryRequest = $queryRequest;        
        $mapRequest->layersRequest = new LayersRequest();
        $mapRequest->layersRequest->layerIds = 
                    array('POLYGON1', 'line', 'point');
        
        return $mapRequest;
    }
    
    /**
     * Returns a {@link MapRequest} for a query with no attributes
     * @return MapRequest
     */
    private function getMapRequestNoAttributes() {
    
        $mapRequest = $this->getMapBboxRequestAllLayers();
        $mapRequest->queryRequest->defaultTableFlags->returnAttributes = false;        
        return $mapRequest;
    }

    /**
     * Returns a {@link MapRequest} for a query with no table
     * @return MapRequest
     */
    private function getMapRequestNoTable() {
    
        $mapRequest = $this->getMapBboxRequestAllLayers();
        $mapRequest->queryRequest->defaultTableFlags->returnTable = false;        
        return $mapRequest;
    }

    /**
     * Returns a {@link MapRequest} for a query on some layers
     * @return MapRequest
     */
    private function getMapBboxRequestUseInQuery() {
    
        $queryRequest = new QueryRequest();
        $bbox = new Bbox();
        $bbox->setFromBbox(-0.75, 51, 0.75, 51.5);
        $queryRequest->shape = $bbox;
        $queryRequest->queryAllLayers = false;
        $querySelections = array();
        $querySelection = new QuerySelection();
        $querySelection->layerId = 'POLYGON1';
        $querySelection->useInQuery = true;
        $querySelection->tableFlags = new TableFlags();
        $querySelection->tableFlags->returnTable = true;
        $querySelections[] = $querySelection;
        $querySelection = new QuerySelection();
        $querySelection->layerId = 'line';
        $querySelection->useInQuery = true;
        $querySelection->tableFlags = new TableFlags();
        $querySelection->tableFlags->returnTable = true;
        $querySelections[] = $querySelection;
        $querySelection = new QuerySelection();
        $querySelection->layerId = 'point';
        $querySelection->useInQuery = true;
        $querySelection->tableFlags = new TableFlags();
        $querySelection->tableFlags->returnTable = true;
        $querySelections[] = $querySelection;
        $queryRequest->querySelections = $querySelections;

        $mapRequest = $this->createRequest();
        $mapRequest->queryRequest = $queryRequest;        
        
        return $mapRequest;
    }

    /**
     * Returns a {@link MapRequest} for a query on layer 'grid_defaulthilight'
     * @return MapRequest
     */
    private function getMapRequestGrid() {
    
        $queryRequest = new QueryRequest();
        $bbox = new Bbox();
        $bbox->setFromBbox(-0.75, 51, 0.75, 52);
        $queryRequest->shape = $bbox;
        $queryRequest->queryAllLayers = false;
        $querySelections = array();
        $querySelection = new QuerySelection();
        $querySelection->layerId = 'grid_defaulthilight';
        $querySelection->useInQuery = true;
        $querySelection->selectedIds = array('10');
        $querySelection->tableFlags = new TableFlags();
        $querySelection->tableFlags->returnTable = true;
        $querySelections[] = $querySelection;
        $queryRequest->querySelections = $querySelections;

        $mapRequest = $this->createRequest();
        $mapRequest->queryRequest = $queryRequest;        
        
        return $mapRequest;
    }
    
    /**
     * Checks for query using point like query
     * @param QueryResult
     */
    private function assertQueryPointResultWithAttributes($queryResult) {

        $this->assertEquals(1, count($queryResult->tableGroup->tables));
        $this->assertEquals("more_points", 
                            $queryResult->tableGroup->tables[0]->tableId);

        $rows = $queryResult->tableGroup->tables[0]->rows;
        
         
        $this->assertEquals(1, count($rows));
        
        $this->assertEquals(array('N'), 
                            $rows[0]->cells);        
    }
    
    /**
     * Checks for query with attributes
     * @param QueryResult
     */
    private function assertQueryResultWithAttributes($queryResult) {

        $this->assertEquals(3, count($queryResult->tableGroup->tables));
        $this->assertEquals("POLYGON1", 
                            $queryResult->tableGroup->tables[0]->tableId);

        $polygonRows = $queryResult->tableGroup->tables[0]->rows; 
        $this->assertEquals(1, count($polygonRows));
        $this->assertEquals('1', $polygonRows[0]->rowId); 
        $this->assertEquals(array('1', 'Cé bô le françès'), 
                            $polygonRows[0]->cells);        
    }
    
    /**
     * Checks for query using polygon like query
     * @param QueryResult
     */
    private function assertQueryPolygonResultWithAttributes($queryResult) {

        $this->assertEquals(3, count($queryResult->tableGroup->tables));
        $this->assertEquals("POLYGON1", 
                            $queryResult->tableGroup->tables[0]->tableId);

        $polygonRows = $queryResult->tableGroup->tables[0]->rows; 
        $this->assertEquals(1, count($polygonRows));
        $this->assertEquals('1', $polygonRows[0]->rowId); 
        $this->assertEquals(array('1', 'Cé bô le françès'), 
                            $polygonRows[0]->cells);        
    }

    /**
     * Checks for query using circle like query
     * @param QueryResult
     */
    private function assertQueryCircleResultWithAttributes($queryResult) {

        $this->assertEquals(3, count($queryResult->tableGroup->tables));
        $this->assertEquals("POLYGON1", 
                            $queryResult->tableGroup->tables[0]->tableId);

        $polygonRows = $queryResult->tableGroup->tables[0]->rows; 
        $this->assertEquals(1, count($polygonRows));
        $this->assertEquals('1', $polygonRows[0]->rowId); 
        $this->assertEquals(array('1', 'Cé bô le françès'), 
                            $polygonRows[0]->cells);        
    }
    
    /**
     * Checks for query with no attributes
     * @param QueryResult
     */
    private function assertQueryResultNoAttributes($queryResult) {

        $this->assertEquals(3, count($queryResult->tableGroup->tables));
        $this->assertEquals("POLYGON1", 
                            $queryResult->tableGroup->tables[0]->tableId);

        $polygonRows = $queryResult->tableGroup->tables[0]->rows; 
        $this->assertEquals(1, count($polygonRows));
        $this->assertEquals('1', $polygonRows[0]->rowId); 
        $this->assertEquals(array(), $polygonRows[0]->cells);        
    }

    /**
     * Checks for query with no table
     * @param QueryResult
     */
    private function assertQueryResultNoTable($queryResult) {

        $this->assertEquals(3, count($queryResult->tableGroup->tables));
        $this->assertEquals("POLYGON1", 
                            $queryResult->tableGroup->tables[0]->tableId);

        $polygonRows = $queryResult->tableGroup->tables[0]->rows; 
        $this->assertEquals(0, count($polygonRows));
    }

    /**
     * Checks for query returning Ids
     * @param QueryResult
     * @param array Ids to be checked
     */
    private function assertQueryResultIds($queryResult, $ids) {

        $this->assertEquals(1, count($queryResult->tableGroup->tables));
        $this->assertEquals("grid_defaulthilight", 
                            $queryResult->tableGroup->tables[0]->tableId);

        $gridRows = $queryResult->tableGroup->tables[0]->rows; 

        $this->assertEquals(count($ids), count($gridRows));
        foreach ($ids as $key => $id) {
            $this->assertEquals($id, $gridRows[$key]->rowId); 
        }
    }

    /**
     * Tests a query on all selected layers
     * using a point like query
     * @param boolean
     */
    public function testQueryPointAllLayers($direct = false) {

        $mapRequest = $this->getMapPointRequestAllLayers();
        $mapResult = $this->getMap($mapRequest);
        $this->assertQueryPointResultWithAttributes($mapResult->queryResult);

        $this->redoDirect($direct, __METHOD__);
    }
    
    /**
     * Tests a query on all selected layers
     * using a rubber band (box) like query
     * @param boolean
     */
    public function testQueryBboxAllLayers($direct = false) {

        $mapRequest = $this->getMapBboxRequestAllLayers();
        // FIXME: Bug 1343
        /*$mapResult = $this->getMap($mapRequest);
        $this->assertQueryResultWithAttributes($mapResult->queryResult);*/
        
        $this->redoDirect($direct, __METHOD__);
    }

    /**
     * Tests a query on all selected layers
     * using a polygon like query
     * @param boolean
     */
    public function testQueryPolygonAllLayers($direct = false) {

        $mapRequest = $this->getMapPolygonRequestAllLayers();
        // FIXME: Bug 1343
        /*$mapResult = $this->getMap($mapRequest);
        $this->assertQueryPolygonResultWithAttributes($mapResult->queryResult);*/

        $this->redoDirect($direct, __METHOD__);
    }

    /**
     * Tests a query on all selected layers
     * using a circle like query
     * @param boolean
     */
    public function testQueryCircleAllLayers($direct = false) {

        $mapRequest = $this->getMapCircleRequestAllLayers();
        // FIXME: Bug 1343
        /* $mapResult = $this->getMap($mapRequest);
        $this->assertQueryCircleResultWithAttributes($mapResult->queryResult);*/

        $this->redoDirect($direct, __METHOD__);
    }
    
    /**
     * Tests a query in mask mode
     * @param boolean
     */
    public function testQueryWithMask($direct = false) {

        $mapRequest = $this->getMapBboxRequestAllLayers();
        $mapRequest->queryRequest->defaultMaskMode = true;
        // FIXME: Bug 1343
        /*$mapResult = $this->getMap($mapRequest);
        $this->assertQueryResultWithAttributes($mapResult->queryResult);*/

        $this->redoDirect($direct, __METHOD__);
    }

    /**
     * Tests a query with no attributes
     * @param boolean
     */
    public function testQueryNoAttributes($direct = false) {

        $mapRequest = $this->getMapRequestNoAttributes();
        // FIXME: Bug 1343
        /* $mapResult = $this->getMap($mapRequest);
        $this->assertQueryResultNoAttributes($mapResult->queryResult);*/

        $this->redoDirect($direct, __METHOD__);
    }

    /**
     * Tests a query with no table
     * @param boolean
     */
    public function testQueryNoTable($direct = false) {

        $mapRequest = $this->getMapRequestNoTable();
        // FIXME: Bug 1343
        /* $mapResult = $this->getMap($mapRequest);
        $this->assertQueryResultNoTable($mapResult->queryResult);*/

        $this->redoDirect($direct, __METHOD__);
    }

    /**
     * Tests a query on some layers
     * @param boolean
     */
    public function testQueryUseInQuery($direct = true) {

        $mapRequest = $this->getMapBboxRequestUseInQuery();
        $mapResult = $this->getMap($mapRequest);

        $this->assertQueryResultNoAttributes($mapResult->queryResult);

        $this->redoDirect($direct, __METHOD__);
    }

    /**
     * Tests 'union' Ids merge policy
     * @param boolean
     */
    public function testQueryPolicyUnion($direct = true) {
        
        $mapRequest = $this->getMapRequestGrid();
        $mapRequest->queryRequest->querySelections[0]->policy
                                        = QuerySelection::POLICY_UNION;
        $mapResult = $this->getMap($mapRequest);

        $this->assertQueryResultIds($mapResult->queryResult,
                                    array('10', '11', '12', '13'));

        $this->redoDirect($direct, __METHOD__);
    }

    /**
     * Tests 'xor' Ids merge policy
     * @param boolean
     */
    public function testQueryPolicyXor($direct = true) {
        
        $mapRequest = $this->getMapRequestGrid();
        $mapRequest->queryRequest->querySelections[0]->policy
                                        = QuerySelection::POLICY_XOR;
        $mapResult = $this->getMap($mapRequest);

        $this->assertQueryResultIds($mapResult->queryResult,
                                    array('11', '12', '13'));

        $this->redoDirect($direct, __METHOD__);
    }

    /**
     * Tests 'intersection' Ids merge policy
     * @param boolean
     */
    public function testQueryPolicyIntersection($direct = true) {
        
        $mapRequest = $this->getMapRequestGrid();
        $mapRequest->queryRequest->querySelections[0]->policy
                                        = QuerySelection::POLICY_INTERSECTION;
        $mapResult = $this->getMap($mapRequest);

        $this->assertQueryResultIds($mapResult->queryResult,
                                    array('10'));

        $this->redoDirect($direct, __METHOD__);
    }
}
?>
