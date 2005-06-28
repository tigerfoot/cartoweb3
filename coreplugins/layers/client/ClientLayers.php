<?php
/**
 * Layers selection interface
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
 * @package CorePlugins
 * @author Sylvain Pasche, Alexandre Saunier
 * @version $Id$
 */

/**
 * Container for session-saved data. See also {@link ClientLayers}.
 * @package CorePlugins
 */
class LayersState {
   
    /**
     * @var array
     */
    public $layersData;
    
    /**
     * @var array
     */
    public $hiddenSelectedLayers;

    /**
     * @var array
     */
    public $hiddenUnselectedLayers;
    
    /**
     * @var array
     */
    public $frozenSelectedLayers;
    
    /**
     * @var array
     */
    public $frozenUnselectedLayers;

    /**
     * @var array
     */
    public $nodesIds;
    
    /**
     * @var array
     */
    public $dropDownSelected;
    
    /**
     * @var string
     */
    public $switchId;
}

/**
 * Model object for layer nodes. Each node contain a reference to a BaseLayer,
 * as defined in the MapInfo structure.
 * This object has knowledge of its children and is capable of recursively
 * traversing the tree.
 */
class LayerNode {
 
    /**
     * A reference to the layer object defined in MapInfo.
     * @var LayerBase 
     */
    public $layer;

    /**
     * The list of children of this layer. Empty if no children.
     * @var array Array of LayerBase objects
     */
    public $children;
    
    /**
     * Assign a layer to this node, and recursively set this node's children.
     * The argument passed to this method is a flat associative array of
     * (layerId => Layer) elements.
     * This method is used to initialize the node tree from a flat list, as
     * given by the MapInfo object.
     * 
     * @param array flat map of layerId's to layer objects. This object will be
     * given to this node children recursively, to let them build the sub nodes
     * of the tree
     */
    public function setChildren($layersMap, $switchId) {
    
        $this->children = array();
        if (!isset($this->layer->children))
            return;
        foreach($this->layer->getChildren($switchId) as $child) {
            $childNode = new LayerNode();
            if (!isset($layersMap[$child]))
                throw new CartoclientException("Child layer $child not found");
            $childNode->layer = $layersMap[$child];
            $childNode->setChildren($layersMap, $switchId);
            $this->children[$childNode->layer->id] = $childNode;
        }
    }
    
    /**
     * Clones a tree of nodes. The layers referenced by the nodes are shallow
     * copied.
     */
    public function __clone() {
        // Warning: $layer property is not cloned !
        $layerNode = new LayerNode();
        $layerNode->layer = $this->layer;
        $layerNode->children = array();
        foreach($this->children as $childId => $child) {
            $layerNode->children[$childId] = clone $child;
        }
        return $layerNode;
    }
    
    /**
     * Removes a child from this node.
     * 
     * @param string the id of the child to remove.
     */
    public function dropChild($id) {
        unset($this->children[$id]);
    }

    /**
     * Recursive method to remove unwanted nodes from this tree. 
     * The callback function will be called with a node as argument. If it
     * returns true, the node will be removed from the tree.
     * 
     * @param callback the callback function to call to filter the unwated
     * nodes. It may be a function string, or layer of object, method.
     */
    public function filterNodes($filterCallback) {
        if (call_user_func($filterCallback, $this)) {
            $this->children = array();
            return true;
        }
        foreach ($this->children as $childId => $child) {
            if ($child->filterNodes($filterCallback))
                unset($this->children[$childId]);
        }
        return false;
    }
    
    /**
     * Flattens this tree of nodes to a map of (layerId => Layer object)
     * 
     * @return the flattened map of layer objects, indexed by their id.
     */
    public function getLayersMap($layersMap) {
        // The $layer->children property will be changed desctructively to 
        //  match the children of this node.   
           
        foreach ($this->children as $child) {
            $layersMap = $child->getLayersMap($layersMap);
        }
        if ($this->layer instanceof LayerContainer) 
            $this->layer->setChildren(array_keys($this->children));
        $layersMap[$this->layer->id] = $this->layer;
        return $layersMap;
    }  
}

/**
 * Handles layers selection interface
 * @package CorePlugins
 */
class ClientLayers extends ClientPlugin
                   implements Sessionable, GuiProvider, ServerCaller, 
                              Exportable, InitUser {
    /**
     * @var Logger
     */
    private $log;
    
    /**
     * @var Smarty_Plugin 
     */
    private $smarty;

    /**
     * @var LayersInit
     */
    private $layersInit;

    /**
     * @var LayersState
     */
    private $layersState;

    /**
     * List of LayerState objects. See {@link LayerState}.
     * @var array
     */
    private $layersData;
    
    /**
     * @var array
     */
    private $hiddenSelectedLayers;
    
    /**
     * @var array
     */
    private $hiddenUnselectedLayers;

    /**
     * @var array
     */
    private $frozenSelectedLayers;
    
    /**
     * @var array
     */
    private $frozenUnselectedLayers;
    
    /**
     * @var array
     */
    private $layers;
    
    /**
     * @var array
     */
    private $selectedLayers = array();
    
    /**
     * @var array
     */
    private $hiddenLayers = array();
    
    /**
     * @var array
     */
    private $frozenLayers = array();
    
    /**
     * @var array
     */
    private $unfoldedLayerGroups = array();
    
    /**
     * @var array
     */
    private $unfoldedIds = array();
    
    /**
     * @var array
     */
    private $nodeId = array();

    /**
     * @var array
     */
    private $nodesIds = array();

    /**
     * @var array
     */
    private $layerIds = array();
    
    /**
     * @var array
     */
    private $childrenCache = array();

    /**
     * @var float
     */
    private $currentScale;
    
    /**
     * @var string
     */
    private $mapId;

    /**
     * Availability information icons
     */
    private $notAvailableIcon;
    private $notAvailablePlusIcon;
    private $notAvailableMinusIcon;

    /**
     * True if switch was overrided by another plugin
     * @param boolean
     */
    private $overrideSwitch = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->log =& LoggerManager::getLogger(__CLASS__);
        parent::__construct();
    }

    /**
     * @see InitUser::handleInit()
     */
    public function handleInit($layersInit) {
        $this->layersInit = $layersInit;
        
        $this->notAvailableIcon = $layersInit->notAvailableIcon;
        $this->notAvailablePlusIcon = $layersInit->notAvailablePlusIcon;
        $this->notAvailableMinusIcon = $layersInit->notAvailableMinusIcon;
    }

    /**
     * Retrieves session-saved layers data.
     * @see Sessionable::loadSession()
     */
    public function loadSession($sessionObject) {
        $this->log->debug('loading session:');
        $this->log->debug($sessionObject);
        $this->layersState = $sessionObject;
        
        $this->layersData =& $this->layersState->layersData;

        $this->hiddenSelectedLayers 
            =& $this->layersState->hiddenSelectedLayers;
        
        $this->hiddenUnselectedLayers
            =& $this->layersState->hiddenUnselectedLayers;
        
        $this->frozenSelectedLayers
            =& $this->layersState->frozenSelectedLayers;
        
        $this->frozenUnselectedLayers
            =& $this->layersState->frozenUnselectedLayers;

        $this->nodesIds =& $this->layersState->nodesIds;
    }

    /**
     * Gets a new instance of layers-plugin session-storage class.
     * Usefull in extended ClientLayers class when using 
     * extended session storage class. 
     * @return LayersState
     */
    private function getNewSessionObject() {
        return new LayersState;
    }

    /**
     * Initializes layers session data and initially populates some properties.
     * @see Sessionable::CreateSession()
     */
    public function createSession(MapInfo $mapInfo, 
                                  InitialMapState $initialMapState) {
        $this->log->debug('creating session:');

        $this->layersState = $this->getNewSessionObject();
        
        $this->layersState->layersData =& $this->layersData;
        
        $this->layersState->hiddenSelectedLayers =& $this->hiddenSelectedLayers;
        
        $this->layersState->hiddenUnselectedLayers
            =& $this->hiddenUnselectedLayers;
        
        $this->layersState->frozenSelectedLayers =& $this->frozenSelectedLayers;
        
        $this->layersState->frozenUnselectedLayers
            =& $this->frozenUnselectedLayers;
            
        $this->layersData = array();
        if (!is_null($initialMapState->layers)) {
            foreach ($initialMapState->layers as $initialLayerState) {
                $this->layersData[$initialLayerState->id] = $initialLayerState;
            }
        }

        $this->hiddenUnselectedLayers = array();
        $this->hiddenSelectedLayers = $this->fetchHiddenSelectedLayers('root');

        $this->frozenUnselectedLayers = array();
        $this->frozenSelectedLayers = $this->fetchFrozenSelectedLayers('root');
        
        $this->selectedLayers = array(); // resets selectedLayers array

        foreach ($this->getLayers() as $layer) {
            if (!isset($this->layersData[$layer->id])) {
                $this->layersData[$layer->id] = new LayerState;
                $this->layersData[$layer->id]->id = $layer->id;
            }
            if ($layer instanceof LayerGroup || $layer instanceof Layer) {
                $this->nodesIds[] = $layer->id;
            }
        }

        $this->layersState->nodesIds =& $this->nodesIds;
    }

    /**
     * Callback used to remove nodes which are not visible by the current user.
     * If it returns true, the node will be ignored in the tree.
     * @param LayerNode The node on which to check access
     * @return boolean True of this node is not accepted (meaning access denied)
     */
    public function nodesFilterSecurity(LayerNode $node) {
        
        // TODO: add constants for security_view
        
        $roles = ConfigParser::parseArray($node->layer->
                                                getMetadata('security_view'));
        if (empty($roles))
            return false;
        return !SecurityManager::getInstance()->hasRole($roles);
    }

    /**
     * Construct a new tree of layerNodes by getting the layers from the
     * mapInfo.
     * @return LayerNode The root node of the hierarchy of layerNodes.
     */
    private function getLayerNode() {
        
        $layers = $this->layersInit->layers;
                
        $layersMap = array();
        foreach ($layers as $layer) {
            $layersMap[$layer->id] = $layer;
        }
        $layerNode = new LayerNode();
        $layerNode->layer = $layersMap['root'];
        $layerNode->setChildren($layersMap, $this->layersState->switchId);

        return $layerNode;
    } 

    /**
     * Filters the layers which are not allowed to be viewed by the current
     * user. It returns a flat map of (layerId => Layer object).
     * @return array An array of LayerBase object.
     */
    private function getLayersSecurityFiltered() {
    
        if (!$this->getConfig()->applySecurity)
            return $this->layersInit->layers;

        // TODO: Analyse the performances of the layerNode tree creation and 
        //  filtering
        
        $layerNode = $this->getLayerNode();     
        $layerNode->filterNodes(array($this, 'nodesFilterSecurity'));
        return $layerNode->getLayersMap(array());
    }

    /**
     * @return array layerIds, list of server-asked layers
     */
    public function getLayerIds() {
        return $this->layerIds;
    }

    /**
     * Returns the list of Layer|LayerGroup|LayerClass objects available 
     * in MapInfo.
     * @return array
     */
    private function getLayers() {
        if(!is_array($this->layers)) {
            $this->layers = array();
            foreach ($this->getLayersSecurityFiltered() as $layer)
                $this->layers[$layer->id] = $layer;
        }
        return $this->layers;
    }

    /**
     * Returns the Layer|LayerGroup|LayerClass object whose name is passed.
     * @param string name of layer
     * @param boolean if true (default), throws exception if invalid layername
     * @return LayerBase layer object of type Layer|LayerGroup|LayerClass
     */
    private function getLayerByName($layername, $strict = true) {
        $layers =& $this->getLayers();
        if (isset($layers[$layername])) 
            return $layers[$layername];
        elseif ($strict)
            throw new CartoclientException("unknown layer name: $layername");
        return false;
    }

    /**
     * Returns a list of current layer children, taking into account some
     * criteria such as aggregation, LayerClass name validity.
     * @param LayerBase layer object of type Layer|LayerGroup|LayerClass
     * @return array array of layers names
     */
    private function getLayerChildren($layer) {
        if(isset($this->childrenCache[$layer->id]))
            return $this->childrenCache[$layer->id];

        if ((!$layer instanceof LayerGroup || !isset($layer->aggregate) || 
             !$layer->aggregate) && !empty($layer->children) && 
             is_array($layer->children)) {
            
            // layer has children which are not aggregated OR has children
            // but is not a layerGroup (ie is a Layer):
            
            $children = array();
            foreach ($layer->getChildren($this->layersState->switchId) as $child) {
                if (!in_array($child, $this->getHiddenLayers()))
                    $children[] = $child;
            }
            
        } elseif (isset($layer->aggregate) && $layer->aggregate) {
            
            // layer is a LayerGroup with aggregated children:
            
            $children = $this->getClassChildren($layer);
        
        } else $children = array();
       
        // may impact children displaying, see method definition
        $this->fetchLayerIcon($layer, $children);

        $this->childrenCache[$layer->id] = $children;
        return $children;
    }
    
    /**
     * Handles switches form
     * @param array
     */
    private function handleSwitches($request) {
        
        if (!$this->overrideSwitch) {
            $this->layersState->switchId = $this->getHttpValue($request, 'switch_id');           
        }    
    }

    /**
     * Changes switch from another plugin
     * @param string
     */
    public function setSwitch($newSwitch) {
        
        $this->overrideSwitch = true;
        $this->layersState->switchId = $newSwitch;
    }

    /**
     * Handles layers-related POST'ed data and updates layers statuses.
     * @see GuiProvider::handleHttpPostRequest() 
     */
    public function handleHttpPostRequest($request) {
        $this->log->debug('update form:');
        $this->log->debug($this->layersState);

        $this->handleSwitches($request);

        // input mask
        $mask = array_diff(array_values($this->nodesIds),
                           $this->getHiddenLayers(), $this->getFrozenLayers());

        // selected dropdowns:
        $this->layersState->dropDownSelected = array();

        // selected layers:
        if (!isset($request['layers'])) $request['layers'] = array();
        foreach ($request as $k => $v) {
        
            if (strstr($k, 'layers_dropdown_')) {
                $id = substr($k, 16); // 16 = strlen('layers_dropdown_')
                $this->layersState->dropDownSelected[$id] = $v;
            } elseif (strstr($k, 'layers_') && 
                      !in_array($v, $request['layers'])) {
            
                $request['layers'][] = $v;
                unset($request[$k]);
            }
        }
        $this->log->debug('requ layers');
        $this->log->debug($request['layers']);
 
        foreach ($mask as $layerId) {
            $this->layersData[$layerId]->selected = in_array($layerId,
                                                           $request['layers']);
        }

        // unfolded layergroups:
        // TODO: use "selected layers"-like mask to keep asleep unfolded nodes
        foreach ($this->getLayers() as $layer) {
            $this->layersData[$layer->id]->unfolded = false;
        }

        if (!isset($request['openNodes'])) {
            $request['openNodes'] = false;
        }
        $openNodes = array_unique(explode(',', $request['openNodes']));

        foreach ($openNodes as $nodeId) {
            if (isset($this->nodesIds[$nodeId]))
                $this->layersData[$this->nodesIds[$nodeId]]->unfolded = true;
        }
    }

    /**
     * Handles data from GET request. Not used/implemented yet.
     * @see GuiProvider::handleHttpGetRequest()
     */
    public function handleHttpGetRequest($request) {}
    
    /**
     * Returns a list of layers that match passed condition.
     * Is in fact a code factorizer for get*Layers() methods.
     * @param string name of some LayerBase property. See {@link LayerBase}.
     * @param string name of ClientLayers property that contains data
     * @param boolean if true, refreshes storage content (default to false)
     * @return array
     */
    private function getMatchingLayers($stateProperty, $storageName,
                                       $refresh = false) {
        if($refresh || !$this->$storageName || 
           !is_array($this->$storageName)) {
            $this->$storageName = array();
            foreach ($this->getLayers() as $layer) {
                if (isset($this->layersData[$layer->id]) &&
                    isset($this->layersData[$layer->id]->$stateProperty) &&
                    $this->layersData[$layer->id]->$stateProperty)
                    $this->{$storageName}[] = $layer->id;
            }
        }
        return $this->$storageName;
    }

    /**
     * Returns the list of activated layers.
     * @param boolean optional (default: false), if true, forces result refresh
     * @return array
     */
    private function getSelectedLayers($refresh = false) {
        return $this->getMatchingLayers('selected', 'selectedLayers', 
                                        $refresh);
    }

    /**
     * Returns the list of LayerGroups that must be rendered unfolded.
     * @return array
     */
    private function getUnfoldedLayerGroups() {
        return $this->getMatchingLayers('unfolded', 'unfoldedLayerGroups');
    }

    /**
     * Returns the list of explicitely hidden layers.
     * @return array
     */
    private function getHiddenLayers() {
        return $this->getMatchingLayers('hidden', 'hiddenLayers');
    }

    /**
     * Returns the list of explicitely frozen layers.
     * @return array
     */
    private function getFrozenLayers() {
        return $this->getMatchingLayers('frozen', 'frozenLayers');
    }

    /**
     * Recursively retrieves selected hidden layers (not transmitted by the
     * browser). Since "hidden" property is inheritated by layers
     * from their declared-as-hidden parents, those layers selection statuses
     * are retrieved as well.
     * @param string layer name
     * @param boolean (default: false) if true, transmits 'hidden' status to 
     * children layers
     * @param boolean (default: false) if true, transmits 'selected' status to
     * children layers
     * @return array
     */
    private function fetchHiddenSelectedLayers($layerId, 
                                               $forceHidden = false,
                                               $forceSelected = false) {
        $layer = $this->getLayerByName($layerId);
        if (!$layer || $layer instanceof LayerClass) return array();

        return $this->fetchRecursively($layer, 'hidden',
                                       $forceHidden, $forceSelected);
    }

    /**
     * Recursively retrieves selected frozen layers.
     * @param string layer name
     * @param boolean (default: false) if true, transmits 'frozen' status to
     * children layers
     * @param boolean (default: false) if true, transmits 'selected' status to
     * children layers
     * @return array
     * @see fetchHiddenSelectedLayers()
     */
    private function fetchFrozenSelectedLayers($layerId,
                                               $forceFrozen = false,
                                               $forceSelected = false) {
        $layer = $this->getLayerByName($layerId);
        if (!$layer || $layer instanceof LayerClass ||
            in_array($layerId, $this->hiddenSelectedLayers) ||
            in_array($layerId, $this->hiddenUnselectedLayers))
            return array();

        return $this->fetchRecursively($layer, 'frozen',
                                       $forceFrozen, $forceSelected);
    }

    /**
     * Performs common recursive job for fetchHiddenSelectedLayers() and
     * fetchFrozenSelectedLayers().
     * @param LayerBase
     * @param string type of layers detection: 'hidden' or 'frozen'
     * @param boolean inherited status (see above type)
     * @param boolean inherited selection status
     * @return array
     * @see fetchHiddenSelectedLayers()
     * @see fetchFrozenSelectedLayers()
     */
    private function fetchRecursively($layer, $type, 
                                      $forceFixed, $forceSelected) {
        $getFixedLayers = 'get' . ucfirst($type) . 'Layers';
        $fixedUnselectedLayers = $type . 'UnselectedLayers';
        $fetchFixedSelectedLayers = 'fetch' . ucfirst($type) . 'SelectedLayers';
        
        $fixedSelectedLayers = array();
        
        // $forceFixed: "fixed" status is inheritated by children layers.
        // $forceSelected: is true if parent was selected...
        $isFixed = $forceFixed ||
                    in_array($layer->id, $this->$getFixedLayers());
        if ($isFixed) {
            if ($forceSelected ||
                in_array($layer->id, $this->getSelectedLayers())) {
                $isSelected = true;
                $fixedSelectedLayers[] = $layer->id;
            } else {
                $isSelected = false;
                $this->{$fixedUnselectedLayers}[] = $layer->id;
            }
        }

        foreach ($layer->getChildren($this->layersState->switchId) as $child) {
            $newList = $this->$fetchFixedSelectedLayers($child, $isFixed,
                                                      $isFixed && $isSelected);
            if ($newList) {
                $fixedSelectedLayers = array_merge($fixedSelectedLayers,
                                                   $newList);
                $fixedSelectedLayers = array_unique($fixedSelectedLayers);
            }
        }
        return $fixedSelectedLayers;
    }
    
    /**
     * Determines activated layers by recursively browsing LayerGroups.
     * Only keeps Layer objects that are not detected as {hidden AND 
     * not selected}.
     * @param array list of layers names
     * @return array list of children, grand-children... of given layers
     */
    public function fetchChildrenFromLayerGroup($layersList) {
        if (!$layersList || !is_array($layersList))
            return array();

        $cleanList = array();
        foreach ($layersList as $key => $layerId) {
            $layer = $this->getLayerByName($layerId, false);
            if (!$layer) continue;

            // removes non Layer objects
            if ($layer instanceof Layer) {
                if (in_array($layerId, $this->getSelectedLayers()) ||
                    (!in_array($layerId, $this->hiddenUnselectedLayers) &&
                    !in_array($layerId, $this->frozenUnselectedLayers)))
                    $cleanList[] = $layerId;
                continue;
            }

            // no use to browse more if object is not a LayerGroup
            if (!$layer instanceof LayerGroup) continue;

            // recursively gets sublayers from current layer children
            $newList = $this->fetchChildrenFromLayerGroup(
                           $layer->getChildren($this->layersState->switchId));
            if ($newList) {
                $cleanList = array_merge($cleanList, $newList);
                $cleanList = array_unique($cleanList);
            }
        }       
        return array_unique($cleanList);
    }

    /**
     * Recursively determines layers that can be selected in layers selector.
     * @param string layer id
     * @return array layers list
     */
    private function getLayersMask($layerId = 'root') {
        $layer = $this->getLayerByName($layerId);
        
        if ($layer instanceof LayerClass)
            return array();

        $mask = array($layerId);
        
        if ($layer instanceof Layer)
            return $mask;

        if ($layer->rendering == 'dropdown') {
            if (isset($this->layersState->dropDownSelected[$layerId]))
                $childId = $this->layersState->dropDownSelected[$layerId];
            else {
                $children = $layer->getChildren($this->layersState->switchId);
                $childId = $children[0];
            }

            $children = $this->getLayersMask($childId);
            return array_merge($mask, $children);
        }
        
        foreach ($layer->getChildren($this->layersState->switchId) as $childId) {
            $children = $this->getLayersMask($childId);
            $mask = array_merge($mask, $children);
        }

        return $mask;
    }

    /**
     * @see ServerCaller::buildRequest()
     */
    public function buildRequest() {
        $layersMask = $this->getLayersMask();
        
        $this->layerIds = $this->getSelectedLayers(true);
        $this->layerIds = $this->fetchChildrenFromLayerGroup($this->layerIds);
        $this->layerIds = array_intersect($this->layerIds, $layersMask);
     
        $layersRequest = new LayersRequest();
        $layersRequest->layerIds = $this->layerIds;
        
        $layersRequest->switchId = $this->layersState->switchId;

        return $layersRequest;
    }

    /**
     * @see ServerCaller::initializeResult()
     */
    public function initializeResult($mapResult) {}

    /**
     * @see ServerCaller::handleResult()
     */
    public function handleResult($mapResult) {}

   /**
     * Recursively retrieves the list of Mapserver Classes bound to the layer
     * or its sublayers.
     * @param LayerBase
     * @return array array of layers names
     */
    private function getClassChildren($layer) {
        if ($layer instanceof LayerClass) return array($layer->id);
       
        elseif(!isset($layer->children) || !is_array($layer->children) ||
               !$layer->children)
            return array();

        $classChildren = array();
        foreach ($layer->getChildren($this->layersState->switchId) as $child) {
            $childLayer = $this->getLayerByName($child);
            $sub = $this->getClassChildren($childLayer);
            $classChildren = array_merge($classChildren, $sub);
            $classChildren = array_unique($classChildren);
        }
        return $classChildren;
    }

    /**
     * Retrieves current scale from location plugin
     * @return float
     */
    private function getCurrentScale() {
        if (isset($this->currentScale)) {
            return $this->currentScale;
        } else {
            $pluginManager = $this->getCartoclient()->getPluginManager();
            
            if (!empty($pluginManager->location))
                $this->currentScale = $pluginManager->location->getCurrentScale();
            else
                $this->currentScale = 0;

            return $this->currentScale;
        }
    }
    
    /**
     * @param string icon filename
     * @return boolean True if this icon is an out of range icon 
     * (below or above scale).
     */
    private function isOutOfRangeIcon($icon) {
        return $icon == $this->notAvailableIcon ||
                $icon == $this->notAvailablePlusIcon ||
                $icon == $this->notAvailableMinusIcon;
    }
    
    /**
     * Returns layer icon filename if any.
     * @param LayerBase
     * @param array list of layer children names (default: empty array)
     * @return string
     */
    private function fetchLayerIcon($layer, &$children = array()) {
        if (!$layer->icon || $layer->icon == 'none') {
            $layer->icon = false;
        
            if ($layer instanceof Layer ||
                ($layer instanceof LayerGroup && $layer->aggregate)) {

                if ($this->setOutofScaleIcon($layer)) {
                    $children = array();
                    return $layer->icon;
                }
                
                $nbChildren = count($children);
                if (!$nbChildren) 
                    return false;

                // if layer has no icon, tries using first class icon
                $i = 0;
                do {
                    $childLayer = $this->getLayerByName($children[$i++]);
                    $layer->icon = $this->fetchLayerIcon($childLayer);
                }
                while ($this->isOutOfRangeIcon($layer->icon) &&
                       isset($children[$i]));

                // in addition, if layer has only one class, 
                // does not display it
                if ($nbChildren == 1 || $this->isOutOfRangeIcon($layer->icon)) 
                    $children = array();
            }
        } elseif ($this->setOutofScaleIcon($layer))
            $children = array();
        
        return $layer->icon;
    }

    /**
     * Substitutes out-of-scale icons if current scale is out of the layer
     * range of scales.
     * @param LayerBase
     * @return boolean
     */
    private function setOutofScaleIcon($layer) {
        if ($layer->minScale && 
            $this->getCurrentScale() < $layer->minScale) {
            $layer->icon = $this->notAvailableMinusIcon;
            return true;
        }
        
        if ($layer->maxScale &&
            $this->getCurrentScale() > $layer->maxScale) {
            $layer->icon = $this->notAvailablePlusIcon;
            return true;
        }
        
        // TODO: handle notAvailableIcon
        
        return false;
    }

    /**
     * Deals with every single layer and recursively calls itself 
     * to build sublayers. 
     * @param LayerBase
     * @param boolean (default: false) if true transmits 'selected' status to
     * children
     * @param boolean (default: false) if true transmits 'frozen' status to
     * children
     * @param string (default: 'tree') rendering of layers
     * @param int (default: 0) id of parent layer in displayed interface
     * @return array array of layer children and grand-children... data 
     */
    private function fetchLayer($layer, $forceSelection = false,
                                        $forceFrozen = false,
                                        $layerRendering = 'tree', 
                                        $parentId = 0) {
        
        // if level is root and root is hidden (no layers menu displayed):
        if ($layer->id == 'root' && $this->layersData['root']->hidden)
            return array();

        $element = array();

        // if parent is selected, children are selected too!
        $layerChecked = $forceSelection ||
                        in_array($layer->id, $this->getSelectedLayers());
        $layerFrozen = $forceFrozen ||
                       in_array($layer->id, $this->getFrozenLayers());

        $childrenLayers = array();
        $element['elements'] =& $childrenLayers;
        $childrenRendering = ($layer instanceof LayerGroup && 
                              $layer->rendering) ?
                             $layer->rendering : 'tree';

        $isDropDown = ($layer instanceof LayerGroup && 
                       $layer->rendering == 'dropdown');
        
        if ($isDropDown) {
            $parentId = $layer->id;
            $isRadioContainer = false;
            $dropDownChildren = array();
            if (isset($this->layersState->dropDownSelected[$parentId])) {
                $dropDownSelected = 
                    $this->layersState->dropDownSelected[$parentId];
            } else
                $i = 0;
        } else {
            $isRadioContainer = ($layer instanceof LayerGroup &&
                                 $layer->rendering == 'radio');
        }
        
        $firstChild = true;
        $level = count($this->nodeId);
        if ($layer->id == 'root')
            $nodeId = 0;
        else
            $nodeId = implode('.', $this->nodeId);

        foreach ($this->getLayerChildren($layer) as $child) {
            $childLayer = $this->getLayerByName($child);

            if ($isDropDown) {
                $dropDownChildren[$childLayer->id] =
                                I18n::gt($childLayer->label);
                
                if (isset($dropDownSelected)) {
                    if ($dropDownSelected != $childLayer->id)
                        continue; 
                } elseif ($i++) continue;
            }
          
            if ($layer instanceof LayerGroup) {
                if ($firstChild) {
                    $firstChild = false;
                    $this->nodeId[] = 1;
                } else {
                    $this->nodeId[$level]++;
                }
            }
            $childrenLayers[] = $this->fetchLayer($childLayer, $layerChecked,
                                                  $layerFrozen, 
                                                  $childrenRendering, 
                                                  $layer->id);
        }

        if ($layer instanceof LayerGroup
            && !$layer->aggregate
            && count($childrenLayers) > 0)
            array_pop($this->nodeId);

        $groupFolded = !in_array($layer->id, $this->getUnfoldedLayerGroups());
        $layer->label = I18n::gt($layer->label);
        if ($layer->link) {
            $layer->link = I18n::gt($layer->link);
        }
        $this->nodesIds[$nodeId] = $layer->id;
        $layerOutRange = 0;

        if ($isDropDown) {
            if (!isset($dropDownSelected)) $dropDownSelected = false;
            $element = array_merge($element,
                                 array('dropDownChildren' => $dropDownChildren,
                                       'dropDownSelected' => $dropDownSelected,
                                    ));
        } else {
            $nextscale = false;
            switch($layer->icon) {
                // TODO: handle notAvailableIcon
                case $this->notAvailablePlusIcon;
                    $layerOutRange = 1;
                    if ($layer->maxScale)
                        $nextscale = round(0.99 * $layer->maxScale);
                    break;

                case $this->notAvailableMinusIcon;
                    $layerOutRange = -1;
                    if ($layer->minScale)
                        $nextscale = round(1.01 * $layer->minScale);
                    break;
            }
            $element['nextscale'] = $nextscale;
        }

        if (empty($layer->icon)) {
            $iconUrl = '';
        } else { 
            $resourceHandler = $this->getCartoclient()->getResourceHandler();
            $iconUrl = $resourceHandler->getFinalUrl($layer->icon, false);
        }

        $metadata = $layer->getAllMetadata();
        $metadata['lang'] = LANG;

        $element = array_merge($element,
                          array('layerLabel'       => $layer->label,
                                'layerMeta'        => $metadata,
                                'layerId'          => $layer->id,
                                'layerClassName'   => $layer->className,
                                'layerLink'        => $layer->link,
                                'layerIcon'        => $iconUrl,
                                'layerOutRange'    => $layerOutRange,
                                'layerChecked'     => $layerChecked,
                                'layerFrozen'      => $layerFrozen,
                                'layerRendering'   => $layerRendering,
                                'isDropDown'       => $isDropDown,
                                'isRadioContainer' => $isRadioContainer,
                                'groupFolded'      => $groupFolded,
                                'parentId'         => $parentId,
                                'nodeId'           => $nodeId,
                                ));
        
        if (!$groupFolded) 
            $this->unfoldedIds[] = $nodeId;
        
        return $element;
    }

    /**
     * Initializes layers selector interface.
     * @return string result of a Smarty fetch
     */
    private function drawLayersList() {
    
        $this->smarty = new Smarty_Plugin($this->getCartoclient(), $this);

        $this->nodesIds = array();
        $this->mapId = $this->getCartoclient()->getProjectHandler()->getMapName();
        
        $rootLayer = $this->getLayerByName('root');
        $element = $this->fetchLayer($rootLayer);

        if (!$element) return false;

        $startOpenNodes = implode('\',\'', $this->unfoldedIds);

        $this->smarty->assign(array('element'        => $element,
                                    'startOpenNodes' => $startOpenNodes,
                                    'mapId'          => $this->mapId,
                                    ));
                                    
        return $this->smarty->fetch('layers.tpl');
    }

    /**
     * Draws switches dropdown
     * @return string result of a smarty fetch
     */
    protected function drawSwitches() {

        $this->smarty = new Smarty_Plugin($this->getCartoclient(), $this);
        $switchValues = array(ChildrenSwitch::DEFAULT_SWITCH);
        $switchLabels = array(I18n::gt('Default'));
        $switches = $this->layersInit->switches;
        if (!is_array($switches)) $switches = array();
     
        foreach ($switches as $switch) {
            $switchValues[] = $switch->id;
            $switchLabels[] = I18n::gt($switch->label);            
        }
        
        if (count($switchValues) == 1)
            return '';

        $this->smarty->assign(array('switch_values' => $switchValues,
                                    'switch_labels' => $switchLabels,
                                    'switch_id' => $this->layersState->switchId));
        return $this->smarty->fetch('switches.tpl');            
    }

    /**
     * Assigns the layers interface output in the general CartoClient template.
     * @see GuiProvider::renderForm()
     */
    public function renderForm(Smarty $template) {
        $template->assign('layers', $this->drawLayersList());
        $template->assign('switches', $this->drawSwitches());
    }

    /**
     * Returns icon full path.
     * @param string icon filename
     * @return string full path
     */
    private function getPrintedIconPath($icon) {
        if (!$icon)
            return '';
        
        $resourceHandler = $this->getCartoclient()->getResourceHandler();
        return $resourceHandler->getPathOrAbsoluteUrl($icon, false);
    }

    /**
     * Returns given layer printing data (icon, label, children...).
     * @param string layer id
     * @return array
     */
    private function getPrintedLayerData($layerId) {
        $layer = $this->getLayerByName($layerId, false);
        $scale = $this->getCurrentScale();
        
        if (($layer->maxScale && $scale > $layer->maxScale) ||
            ($layer->minScale && $scale < $layer->minScale))
            return array();
        
        $data = array('label' => I18n::gt($layer->label),
                      'icon' => $this->getPrintedIconPath($layer->icon),
                      'children' => array());
        
        if (!$layer instanceof LayerClass && $layer->children) {    
            $children =& $data['children'];
            foreach ($layer->getChildren($this->layersState->switchId) as $childId)
                $children[] = $this->getPrintedLayerData($childId);
        }

        return $data;
    }

    /**
     * Recursively detects selected layers parent nodes and substitutes them 
     * if parents are aggregated.
     * @param string current layer id
     * @param array selected layers list
     * @param array structure containing data of layers to print
     */
    private function getPrintedParents($layerId, &$selectedLayers, 
                                       &$printedNodes) {
        $layer = $this->getLayerByName($layerId, false);
        
        if (!$layer instanceof LayerGroup || !$layer->children)
            return;
        
        foreach ($layer->getChildren($this->layersState->switchId) as $childId) {
            $key = array_search($childId, $selectedLayers);
            if (is_numeric($key)) {
                // if parent is aggregated, only display parent
                if ($layer->aggregate) {
                    if (!isset($printedNodes[$layerId])) {
                        $printedNodes[$layerId] = array(
                            'label' => I18n::gt($layer->label),
                            'icon' => $this->getPrintedIconPath($layer->icon),
                            'children' => array());
                    }
                    // retrieves layer classes:
                    $printedNodes[$layerId]['children'] = 
                        array_merge($printedNodes[$layerId]['children'],
                                    $printedNodes[$childId]['children']);
                    unset($printedNodes[$childId]);
                }
                unset($selectedLayers[$key]);
            } else {
                $this->getPrintedParents($childId, $selectedLayers,
                                         $printedNodes);
            }
        }
    }

    /**
     * Returns the list of layers actually printed on mainmap as well as their
     * classes and parent LayerGroups if any (mainly used for PDF printing).
     * @param array list of layers explicitely asked to CartoServer
     * @param float scale value
     * @return array complete list of printed layers, layergroups, layerclasses
     */
    public function getPrintedLayers($selectedLayers, $scale) {
        $printedNodes = array();
        $this->currentScale = $scale;
       
        foreach ($selectedLayers as $key => $layerId) {
            $layerData = $this->getPrintedLayerData($layerId);
            if ($layerData)
                $printedNodes[$layerId] = $layerData;
            else
                unset($selectedLayers[$key]);
        }
        
        $this->getPrintedParents('root', $selectedLayers, $printedNodes);
        return $printedNodes;

        // TODO: instead of printing parents at the end of the list,
        // draw them where their aggregated children should have been placed.
    }

    /**
     * Saves layers data in session.
     * @see Sessionable::saveSession()
     */
    public function saveSession() {
        $this->log->debug('saving session:');
        $this->log->debug($this->layersState);

        return $this->layersState;
    }

    /**
     * @return array layersInit
     */
    public function getLayersInit() {
        return $this->layersInit;
    }

    /**
     * @see Exportable::adjustExportMapRequest()
     */
    public function adjustExportMapRequest(ExportConfiguration $configuration,
                                    MapRequest $mapRequest) {
        
        $resolution = $configuration->getResolution();
        if (!is_null($resolution))
            $mapRequest->layersRequest->resolution = $resolution;

        $layerIds = $configuration->getLayerIds();
        if (!is_null($layerIds))
            $mapRequest->layersRequest->layerIds = $layerIds;
    }
}
?>
