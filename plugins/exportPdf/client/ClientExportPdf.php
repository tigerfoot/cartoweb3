<?php
/**
 * @package Plugins
 * @author Alexandre Saunier
 * @version $Id$
 */

require_once CARTOCLIENT_HOME . 'client/ExportPlugin.php';

// TODO: include this file only when generating PDF documents.
// Warning: class PdfGeneral is also used in viewer mode.
require_once dirname(__FILE__) . '/ExportPdfObjects.php';

/**
 * Overall class for PDF generation management.
 * @package Plugins
 */
class ClientExportPdf extends ExportPlugin {

    /**
     * @var Logger
     */
    private $log;
    
    /**
     * @var Smarty_CorePlugin
     */
    private $smarty;

    /**
     * @var PdfGeneral
     */
    private $general;

    /**
     * @var PdfFormat
     */
    private $format;
    
    /**
     * @var PdfBlock
     */
    private $blockTemplate;
    
    /**
     * @var array
     */
    private $blocks = array();

    /**
     * @var array
     */
    private $optionalInputs = array('title', 'note', 'scalebar', 'overview',
                                    'queryResult', 'legend');
    //TODO: display queryResult form option only if available in MapResult

    /**
     * Constructor
     */
    function __construct() {
        $this->log =& LoggerManager::getLogger(__CLASS__);
        parent::__construct();
    }

    /**
     * @return PdfGeneral
     */
    public function getGeneral() {
        return $this->general;
    }

    /**
     * @return PdfFormat
     */
    public function getFormat() {
        return $this->format;
    }

    /**
     * @return array array of activated PdfBlocks
     */
    public function getBlocks() {
        return $this->blocks;
    }

    /**
     * Returns PDF file name.
     * @return string
     */
    public function getFilename() {
        if (preg_match("/^(.*)\[date,(.*)\](.*)$/", 
                       $this->general->filename, $regs)) {
            $this->general->filename = $regs[1] . date($regs[2]) . $regs[3];
        }
        return $this->general->filename;
    }

    /**
     * Returns an array from a comma-separated list string.
     * @param array
     * @param boolean (default: false) true: returns a simplified array
     * @return array
     */
    private function getArrayFromList($list, $simple = false) {
        $list = explode(',', $list);
        $res = array();
        foreach ($list as $d) {
            $d = trim($d);
            if ($simple) $res[] = $d;
            else $res[$d] = I18n::gt($d);
        }
        return $res;
    }

    /**
     * Returns an array from a comma-separated list of a ini parameter.
     * @param string name of ini parameter
     * @param boolean (default: false) true: returns a simplified array
     * @return array
     */
    private function getArrayFromIni($name, $simple = false) {
        $data = $this->getConfig()->$name;
        if (!$data) return array();

        return $this->getArrayFromList($data, $simple);
    }

    /**
     * Updates $target properties with values from $from ones.
     * @param object object to override
     * @param object object to copy
     */
    private function overrideProperties($target, $from) {
        foreach (get_object_vars($from) as $key => $val) {
            $target->$key = $val;
        }
    }

    /**
     * Returns value from $_REQUEST or else from default configuration.
     * @param string name of parameter
     * @param array available values
     * @param array $_REQUEST
     * @return string
     */
    private function getSelectedValue($name, $choices, $request) {
        $name = strtolower($name);
        $reqname = 'pdf' . ucfirst($name);

        if (isset($request[$reqname]) && 
            in_array($request[$reqname], $choices))
            return $request[$reqname];

        if (isset($this->general->{'default' . ucfirst($name)}))
            return $this->general->{'default' . ucfirst($name)};

        return false;
    }

    /**
     * Sorts blocks using $property criterium (in ASC order).
     * @param string name of property used to sort blocks
     */
    private function sortBlocksBy($property) {
        $blocksVars = array_keys(get_object_vars($this->blockTemplate));
        if (!in_array($property, $blocksVars))
            return $this->blocks;

        $sorter = array();
        foreach ($this->blocks as $id => $block) {
            $val = $block->$property;
            if (isset($sorter[$val]))
                array_push($sorter[$val], $id);
            else
                $sorter[$val] = array($id);
        }
        
        ksort($sorter);

        $blocks = array();
        foreach ($sorter as $val) {
            foreach ($val as $id)
                $blocks[$id] = $this->blocks[$id];
        }

        $this->blocks = $blocks;
    }

    /**
     * Instanciates a PdfBlock object.
     * @param array $request
     * @param stdClass INI object
     * @param string object id
     */
    private function createBlock($request, $iniObjects, $id) {
        // removes blocks with no user input if required:
        $pdfItem = 'pdf' . ucfirst($id);
        if (!(isset($request[$pdfItem]) && trim($request[$pdfItem])) &&
            in_array($id, $this->optionalInputs))
            return;
        
        if (isset($iniObjects->blocks->$id))
            $block = $iniObjects->blocks->$id;
        else
            $block = new stdclass();
            
        $this->blocks[$id] = StructHandler::mergeOverride(
                                 $this->blockTemplate,
                                 $block, true);

        $this->blocks[$id]->id = $id;

        if ($id == 'title' || $id == 'note') {
            $this->blocks[$id]->content = 
                stripslashes(trim($request[$pdfItem]));
        }

        elseif($this->blocks[$id]->type == 'table') {
            if ($this->blocks[$id]->caption && 
                !in_array($this->blocks[$id]->caption, $this->blocks)) {
                
                $caption = $this->blocks[$id]->caption;
                $this->createBlock($request, $iniObjects, $caption);
                
                $this->blocks[$caption]->standalone = false;
                
                if (!isset($this->blocks[$caption]->height) && 
                    isset($this->blocks[$id]->height)) {
                    $this->blocks[$caption]->height =
                        $this->blocks[$id]->height;
                }
            }
    
            if ($this->blocks[$id]->headers &&
                !in_array($this->blocks[$id]->headers, $this->blocks)) {
                
                $headers = $this->blocks[$id]->headers;
                $this->createBlock($request, $iniObjects, $headers);
                
                $this->blocks[$headers]->standalone = false;
                $this->blocks[$headers]->content = 
                   $this->getArrayFromList($this->blocks[$headers]->content,
                                           true);
            
                if (!isset($this->blocks[$headers]->height) &&
                    isset($this->blocks[$id]->height)) {
                    $this->blocks[$headers]->height =
                        $this->blocks[$id]->height;
                }
            }
    
            // TODO: handle multi-row tables when getting content from INI 
            // For now we are limited to one single row.
            $this->blocks[$id]->content = $this->getArrayFromList(
                                            $this->blocks[$id]->content, true);
        }

        elseif ($this->blocks[$id]->type == 'legend') {
            do {
                $lastMapRequest = $this->getLastMapRequest();
                $layersCorePlugin = $this->cartoclient->getPluginManager()->
                                    getPlugin('layers');
                
                if (is_null($lastMapRequest) || is_null($layersCorePlugin)) {
                    unset($this->blocks[$id], $lastMapRequest,
                          $layersCorePlugin);
                    break;
                }
                
                $selectedLayers = $lastMapRequest->layersRequest->layerIds;
                if ($this->blocks[$id]->content) {
                    $content =
                        $this->getArrayFromList($this->blocks[$id]->content,
                                                true);
                    // layers whose ids begin with "!" are not displayed
                    // in legend: 
                    foreach ($content as $layerId) {
                        if ($layerId{0} == '!') {
                            $layerId = substr($layerId, 1);
                            $key = array_search($layerId, $selectedLayers);
                            if (is_numeric($key))
                                unset($selectedLayers[$key]);
                        }
                    }
                    $this->blocks[$id]->content = '';
                }
                
                $this->blocks[$id]->content =
                    $layersCorePlugin->getPrintedLayers($selectedLayers,
                                                        $this->getLastScale());
                
                if ($request['pdfLegend'] == 'out')
                    $this->blocks[$id]->inNewPage = true;
            }
            while (false);
        }
    }

    /**
     * Sets PDF settings objects based on $_REQUEST and configuration data.
     * @param array $_REQUEST
     * @see GuiProvider::handleHttpPostRequest()
     */
    function handleHttpPostRequest($request) {
        $this->log->debug('processing exportPdf request');

        $ini_array = $this->getConfig()->getIniArray();
        $iniObjects = StructHandler::loadFromArray($ini_array);

        // TODO: check validity of each exportPdf config object???
        if (!isset($iniObjects->general) || !is_object($iniObjects->general))
            throw new CartoclientException('invalid exportPdf configuration');

        // general settings retrieving
        $this->general = new PdfGeneral;
        $this->overrideProperties($this->general, $iniObjects->general);
        
        $this->general->formats = $this->getArrayFromList(
                                      $this->general->formats, true);
        
        $this->general->resolutions = $this->getArrayFromList(
                                          $this->general->resolutions, true);
        
        $this->general->activatedBlocks = $this->getArrayFromList(
                                              $this->general->activatedBlocks, 
                                              true);
        
        $this->general->selectedFormat = $this->getSelectedValue(
                                             'format',
                                             $this->general->formats,
                                             $request);

        $this->general->selectedResolution = $this->getSelectedValue(
                                             'resolution',
                                             $this->general->resolutions,
                                             $request);

        $this->general->selectedOrientation = $this->getSelectedValue(
                                              'orientation',
                                              array('portrait', 'landscape'),
                                              $request);
        
        // formats settings retrieving
        $sf = $this->general->selectedFormat;
        
        if (!isset($iniObjects->formats->$sf))
            throw new CartoclientException("invalid exportPdf format: $sf");
        
        $this->format = new PdfFormat;
        $this->overrideProperties($this->format, $iniObjects->formats->$sf);
            
        if (!isset($this->format->horizontalMargin))
            $this->format->horizontalMargin = $this->general->horizontalMargin;
        if (!isset($this->format->verticalMargin))
            $this->format->verticalMargin = $this->general->verticalMargin;

        // adapts general settings depending on selected format
        if (isset($this->format->maxResolution) &&
            $this->general->selectedResolution > $this->format->maxResolution)
            $this->general->selectedResolution = $this->format->maxResolution;

        if ($this->general->selectedOrientation == 'portrait') {
            $this->general->width = $this->format->smallDimension;
            $this->general->height = $this->format->bigDimension;
        } else {
            $this->general->width = $this->format->bigDimension;
            $this->general->height = $this->format->smallDimension;
        }

        if (!$this->general->width || !$this->general->height)
            throw new CartoclientException('invalid exportPdf dimensions');

        // blocks settings retrieving
        $this->blockTemplate = new PdfBlock;
        $this->overrideProperties($this->blockTemplate, $iniObjects->template);

        foreach ($this->general->activatedBlocks as $id) {
            $this->createBlock($request, $iniObjects, $id);
        }

        unset($iniObjects);

        // sorting blocks (order of processing)
        $this->sortBlocksBy('weight');
        $this->sortBlocksBy('zIndex');

        $this->log->debug('REQUEST:');
        $this->log->debug($request);
        $this->log->debug('general settings:');
        $this->log->debug($this->general);
        $this->log->debug('format settings:');
        $this->log->debug($this->format);
        $this->log->debug('blocks settings:');
        $this->log->debug($this->blocks);
    }

    /**
     * Not used/implemented yet.
     * @see GuiProvider::handleHttpGetRequest()
     */
    function handleHttpGetRequest($request) {}

    /**
     * @see GuiProvider::renderForm()
     * @param Smarty
     */
    function renderForm(Smarty $template) {

        $template->assign('exportPdf', $this->drawUserForm());
    }

    /**
     * Builds PDF settings user interface.
     * @return string Smarty fetch result
     */
    private function drawUserForm() {
        $this->smarty = new Smarty_CorePlugin($this->getCartoclient(), $this);

        $pdfFormat_options = $this->getArrayFromIni('general.formats');
        $pdfFormat_selected = strtolower($this->getConfig()->
                                         {'general.defaultFormat'});
        
        $pdfResolution_options = $this->getArrayFromIni('general.resolutions');
        $pdfResolution_selected = $this->getConfig()->
                                         {'general.defaultResolution'};

        $pdfOrientation = $this->getConfig()->
                                         {'general.defaultOrientation'};

        $blocks = $this->getArrayFromIni('general.activatedBlocks', 
                                                  true);
        
        $this->smarty->assign(array(
                   'exportScriptPath'       => $this->getExportScriptPath(),
                   'pdfFormat_options'      => $pdfFormat_options,
                   'pdfFormat_selected'     => $pdfFormat_selected,
                   'pdfResolution_options'  => $pdfResolution_options,
                   'pdfResolution_selected' => $pdfResolution_selected,
                   'pdfOrientation'         => $pdfOrientation,
                       ));

        foreach ($this->optionalInputs as $input) {
            $this->smarty->assign('pdf' . ucfirst($input),
                                  in_array($input, $blocks));
        }
        
        return $this->smarty->fetch('form.tpl');
    }

    /**
     * Returns given distance at selected printing resolution.
     * @param float distance in PdfGeneral dist_unit
     * @return int distance in pixels
     */
    private function getNewMapDim($dist) {
        $dist = PrintTools::switchDistUnit($dist,
                                           $this->general->distUnit,
                                           'in');
        $dist *= $this->general->selectedResolution;
        return round($dist);
    }

    /**
     * @return Bbox bbox from last session-saved MapResult.
     */
    private function getLastBbox() {
        $mapResult = $this->getLastMapResult();
        
        if (is_null($mapResult))
            return new Bbox;

        return $mapResult->locationResult->bbox;
    }

    /**
     * @return float scale from last session-saved MapResult.
     */
    private function getLastScale() {
        $mapResult = $this->getLastMapResult();

        if (is_null($mapResult))
            return 0;

         return $mapResult->locationResult->scale;
    }

    /**
     * Builds export configuration.
     * @param boolean true if configuring to get overview map
     * @param Bbox if set, indicates mainmap extent to outline in overview map
     * @return ExportConfiguration
     */
    function getConfiguration($isOverview = false, $mapBbox = NULL) {
        
        $config = new ExportConfiguration();

        $scale = $this->getLastScale();
        $mapWidth = $mapHeight = 0;

        if ($isOverview) {
            // getting overview map
            $renderMap = true;
            $renderScalebar = false;

            $overview = $this->blocks['overview'];

            // overview dimensions
            $mapWidth = $this->getNewMapDim($overview->width);
            $mapHeight = $this->getNewMapDim($overview->height);

            // mainmap outline:
            if (isset($mapBbox)) {
                $outline = new Rectangle($mapBbox->minx, $mapBbox->miny,
                                         $mapBbox->maxx, $mapBbox->maxy);
                $config->setPrintOutline($outline);
            }
            
            // scale:
            if ($this->general->overviewScaleFactor <= 0)
                $this->general->overviewScaleFactor = 1;

            $scale *= $this->general->overviewScaleFactor;
       } else {
            $renderMap = isset($this->blocks['mainmap']);
            $renderScalebar = isset($this->blocks['scalebar']);

            if ($renderMap) {
                $mainmap = $this->blocks['mainmap'];

                // new map dimensions:
                $mapWidth = $this->getNewMapDim($mainmap->width);
                $mapHeight = $this->getNewMapDim($mainmap->height);

                // scale: no change ("paper" scale = "screen" scale)
            }
        }
       
        $config->setRenderMap($renderMap);
        $config->setRenderKeymap(false);
        $config->setRenderScalebar($renderScalebar);

        // map dimensions:
        $config->setMapWidth($mapWidth);
        $config->setMapHeight($mapHeight);
      
        // scale:
        $scale *= $this->general->mapServerResolution;
        $scale /= $this->general->selectedResolution;
        $config->setScale($scale);
        
        // map center coordinates:
        $savedBbox = $this->getLastBbox();
        $xCenter = ($savedBbox->minx + $savedBbox->maxx) / 2;
        $yCenter = ($savedBbox->miny + $savedBbox->maxy) / 2;
        $point = new Point($xCenter, $yCenter);
        $config->setPoint($point);

        $config->setBbox($savedBbox);      
        $config->setZoomType('ZOOM_SCALE');
        $config->setLocationType('zoomPointLocationRequest');

        $this->log->debug('Selected resolution: ' .
                          $this->general->selectedResolution);
        $this->log->debug('Print config:');
        $this->log->debug($config);

        return $config;
    }

    /**
     * Returns current Cartoclient base URL.
     * @return string URL
     */
    private function guessBaseUrl() {
        $url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://' ) .
               $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
        if (preg_match("/^(.*)exportPdf\/(.*)$/", $url, $regs))
            $url = $regs[1];
        return $url;
    }

    /**
     * Returns the absolute URL of $gfx by prepending CartoServer base URL.
     * @param string
     * @return string
     */
    private function getGfxPath($gfx) {
        /* // FIXME: Miniproxy breaks this simplification in direct access mode
           // maybe investigate further
        if ($this->cartoclient->getConfig()->cartoserverDirectAccess) {
            $path =  $this->cartoclient->getConfig()->getBasePath();
            $path .= 'www-data/' . $gfx;
            return $path;
        }
        */
        
        $cartoserverBaseUrl = $this->cartoclient->getConfig()
                              ->cartoserverBaseUrl;
        
        if (is_null($cartoserverBaseUrl) || !$cartoserverBaseUrl)
            $cartoserverBaseUrl = $this->guessBaseUrl();
        
        return $cartoserverBaseUrl . $gfx;
    }

    /**
     * Updates Mapserver-generated maps PdfBlocks with data returned by 
     * CartoServer.
     * @param MapResult
     * @param string name of PdfBlock to update
     * @param string name of MapResult property
     */
    private function updateMapBlock($mapObj, $name, $msName = '') {
        if (!$msName) $msName = $name;

        if (!$mapObj instanceof MapResult ||
            !$mapObj->imagesResult->$msName->isDrawn ||
            !isset($this->blocks[$name]))
            return;

        $map = $mapObj->imagesResult->$msName;
        $block = $this->blocks[$name];

        $block->content = $this->getGfxPath($map->path);
        $block->type = 'image';
        
        if (!isset($block->width)) {
            $width = $map->width / $this->general->selectedResolution;
            $block->width = PrintTools::switchDistUnit($width,
                                       'in', $this->general->distUnit);
        }
        
        if (!isset($block->height)) {
            $height = $map->height / $this->general->selectedResolution;
            $block->height = PrintTools::switchDistUnit($height,
                                       'in', $this->general->distUnit);
        }
    }

    /**
     * Sets mainmap dimensions according to selected format and orientation.
     * @param PdfWriter
     */
    private function setMainMapDim(PdfWriter $pdf) {
        $mapBlock = $this->blocks['mainmap'];
        
        if (!isset($mapBlock->width)) {
            $hmargin = $this->format->horizontalMargin 
                       + $mapBlock->horizontalMargin;
            $mapBlock->width = $pdf->getPageWidth() - 2 * $hmargin; 
        }
        
        if (!isset($mapBlock->height)) {
            $vmargin = $this->format->verticalMargin
                       + $mapBlock->verticalMargin;
            $mapBlock->height = $pdf->getPageHeight() - 2 * $vmargin;
        }
    }

    /**
     * Transforms query results from MapResult into TableElements
     * @param MapResult
     * @return array array of TableElement
     */
    private function getQueryResult($mapResult) {
        if (!$mapResult instanceof MapResult || 
            !isset($mapResult->queryResult))
            return array();

        $results = array();
        foreach ($mapResult->queryResult->layerResults as $layer) {
            if (!$layer->numResults)
                continue;

            $table = new TableElement;
            
            $table->caption = I18n::gt($layer->layerId);
            
            $table->headers = array('Id');
            foreach ($layer->fields as $field)
                $table->headers[] = I18n::gt($field);

            foreach ($layer->resultElements as $res) {
                $row = array($res->id);
                foreach ($res->values as $val)
                    $row[] = $val;
                $table->rows[] = $row;
            }

            $results[] = $table;
        }
    
        return $results;
    }
    
    /**
     * @see ExportPlugin::getExport()
     * @return ExportOutput export result
     */
    function getExport() {

        $pdfClass =& $this->general->pdfEngine;
        
        $pdfClassFile = dirname(__FILE__) . '/' . $pdfClass . '.php';
        if (!is_file($pdfClassFile))
            throw new CartoclientException("invalid PDF engine: $pdfClassFile");
        require_once $pdfClassFile;
 
        $pdf = new $pdfClass($this);
 
        if (isset($this->blocks['mainmap']))
            $this->setMainMapDim($pdf);
 
        // Retrieving of data from CartoServer:
        $mapResult = $this->getExportResult($this->getConfiguration());
 
        if (isset($this->blocks['overview'])) {
            $mapBbox = $mapResult->locationResult->bbox;
            $overviewResult = $this->getExportResult(
                                  $this->getConfiguration(true, $mapBbox));
        } else {
            $overviewResult = false;
        }

        $this->updateMapBlock($mapResult, 'mainmap');
        $this->updateMapBlock($mapResult, 'scalebar');
        $this->updateMapBlock($overviewResult, 'overview', 'mainmap');
        
        $pdf->initializeDocument();
 
        $pdf->addPage();
 
        foreach ($this->blocks as $block) {
            if ($block->multiPage || $block->inNewPage || !$block->standalone)
                continue;

            switch ($block->type) {
                case 'image':
                    $pdf->addGfxBlock($block);
                    break;
                case 'text':
                    $pdf->addTextBlock($block);
                    break;
                case 'legend':
                    $pdf->addLegend($this->blocks['legend']);
                    break;
                default:
                    // ignores block
                // TODO: handle type = pdf
            }
            
        }

        if (isset($this->blocks['legend']) && 
            $this->blocks['legend']->inNewPage) {
            $pdf->addPage();
            $pdf->addLegend($this->blocks['legend']);
        }

        // query results displaying
        if (isset($this->blocks['queryResult'])) {
            $queryResult = $this->getQueryResult($mapResult);        
            if ($queryResult) {
                $this->blocks['queryResult']->content = $queryResult;
                
                $pdf->addPage();
                $pdf->addTable($this->blocks['queryResult']);
            }
        }
        
        // TEMPORARY TEST CODE
        /*if (isset($this->blocks['table'])) {
            require_once(dirname(__FILE__) . '/table.php');
            $tableObj = new TableElement;
            $tableObj->rows = $table;
            $this->blocks['table']->content = $tableObj;

            $pdf->addPage();
            $pdf->addTable($this->blocks['table']);
        }*/
        
        // TODO: handle inLastPages blocks
 
        $contents = $pdf->finalizeDocument();
 
        $output = new ExportOutput();
        $output->setContents($contents);
        return $output;
    }

    /**
     * Writes PDF file on disk.
     * @param string PDF content
     * @return string filename
     */
    private function generatePdfFile($pdfBuffer) {
        $filename = $this->getFilename();
        $filepath = PrintTools::getPdfDir() . '/' . $filename;
        $fp = fopen($filepath, 'w');
        fwrite($fp, $pdfBuffer);
        fclose($fp);
        return $filename;
    }

    /**
     * Returns generated PDF file URL.
     * @param string filename
     * @return string URL
     */
    private function getPdfFileUrl($filename) {
        $urlProvider = $this->cartoclient->getResourceHandler()->getUrlProvider();
        $pdfUrl = $urlProvider->getGeneratedUrl('pdf/' . $filename);
        return $this->guessBaseUrl() . $pdfUrl;
    }

    /**
     * Outputs generated PDF file using config "output" medium.
     * @param string PDF content
     */
    function outputPdf($pdfBuffer) {
        switch ($this->general->output) {
            case 'inline':
                header('Content-type: application/pdf');
                header('Content-Length: ' . strlen($pdfBuffer));
                header('Content-Disposition: inline; filename=' . 
                       $this->getFilename());
                print $pdfBuffer;
                break;

            case 'attachment':
                header('Content-type: application/pdf');
                header('Content-Length: ' . strlen($pdfBuffer));
                header('Content-Disposition: attachment; filename=' .
                       $this->getFilename());
                print $pdfBuffer;
                break;

            case 'link':
                $filename = $this->generatePdfFile($pdfBuffer);
                // TODO: use template
                printf('<a href="%s">%s</a>',
                       $this->getPdfFileUrl($filename),
                       I18n::gt('Click here to display PDF file'));
                break;

            case 'redirection':
            default:
                $filename = $this->generatePdfFile($pdfBuffer);
                header('Content-type: application/pdf');
                header('Location: ' . $this->getPdfFileUrl($filename));
                break;
        }
        exit;
    }
}
?>
