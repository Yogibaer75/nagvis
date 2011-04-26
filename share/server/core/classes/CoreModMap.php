<?php
/*******************************************************************************
 *
 * CoreModMap.php - Core Map module to handle ajax requests
 *
 * Copyright (c) 2004-2011 NagVis Project (Contact: info@nagvis.org)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 ******************************************************************************/

/**
 * @author Lars Michelsen <lars@vertical-visions.de>
 */
class CoreModMap extends CoreModule {
    private $name = null;

    public function __construct(GlobalCore $CORE) {
        $this->sName = 'Map';
        $this->CORE = $CORE;
        $this->htmlBase = $this->CORE->getMainCfg()->getValue('paths', 'htmlbase');

        // Register valid actions
        $this->aActions = Array(
            'getMapProperties'  => 'view',
            'getMapObjects'     => 'view',
            'getObjectStates'   => 'view',
            // WUI specific actions
            'manage'            => REQUIRES_AUTHORISATION,
            'doAdd'             => 'add',
            'doRename'          => 'edit',
            'doDelete'          => 'edit',
            'doExportMap'       => 'edit',
            'doImportMap'       => 'edit',

            'addModify'         => 'edit',
            'modifyObject'      => 'edit',
            'createObject'      => 'edit',
            'deleteObject'      => 'edit',

            'manageTmpl'        => 'edit',
            'getTmplOpts'       => 'edit',
            'doTmplAdd'         => 'edit',
            'doTmplModify'      => 'edit',
            'doTmplDelete'      => 'edit',

            'getObjects'        => 'edit',
        );

        // Register valid objects
        $this->aObjects = $this->CORE->getAvailableMaps(null, SET_KEYS);
    }

    public function initObject() {
        switch($this->sAction) {
            // These have the object in GET var "show"
            case 'getMapProperties':
            case 'getMapObjects':
            case 'getObjectStates':
            case 'manageTmpl':
            case 'getTmplOpts':
            case 'addModify':
                $aVals = $this->getCustomOptions(Array('show' => MATCH_MAP_NAME));
                $this->name = $aVals['show'];
            break;
            // And those have the objecs in the POST var "map"
            case 'doRename':
            case 'doDelete':
            case 'createObject':
            case 'modifyObject':
            case 'deleteObject':
            case 'doTmplAdd':
            case 'doTmplModify':
            case 'doTmplDelete':
            case 'doExportMap':
                $FHANDLER = new CoreRequestHandler(array_merge($_GET, $_POST));
                if($FHANDLER->match('map', MATCH_MAP_NAME))
                    $this->name = $FHANDLER->get('map');
                else
                    new GlobalMessage('ERROR', $this->CORE->getLang()->getText('Invalid query. The parameter [NAME] is missing or has an invalid format.',
                                                                               Array('NAME' => 'map')));
            break;
        }

        // Set the requested object for later authorisation
        $this->setObject($this->name);
    }

    public function handleAction() {
        $sReturn = '';

        if($this->offersAction($this->sAction)) {
            switch($this->sAction) {
                case 'getMapProperties':
                    $sReturn = $this->getMapProperties();
                break;
                case 'getMapObjects':
                    $sReturn = $this->getMapObjects();
                break;
                case 'getObjectStates':
                    $sReturn = $this->getObjectStates();
                break;
                case 'manage':
                    $VIEW = new WuiViewManageMaps($this->AUTHENTICATION, $this->AUTHORISATION);
                    $sReturn = json_encode(Array('code' => $VIEW->parse()));
                break;
                case 'doAdd':
                    $this->handleResponse('handleResponseAdd', 'doAdd',
                                            $this->CORE->getLang()->getText('The map has been created.'),
                                                $this->CORE->getLang()->getText('The map could not be created.'),
                                                1, $this->htmlBase.'/frontend/wui/index.php?mod=Map&act=edit&show='.$_POST['map']);
                break;
                case 'doRename':
                    // if renamed map is open, redirect to new name
                    $FHANDLER = new CoreRequestHandler($_POST);
                    $current = $FHANDLER->get('map_current');
                    $map     = $FHANDLER->get('map');
                    if($current == 'undefined' || $current == '' || $current == $map)
                        $map = $FHANDLER->get('map_new_name');
                    else
                        $map = $current;

                    $this->handleResponse('handleResponseRename', 'doRename',
                                            $this->CORE->getLang()->getText('The map has been renamed.'),
                                                                $this->CORE->getLang()->getText('The map could not be renamed.'),
                                                                1, $this->htmlBase.'/frontend/wui/index.php?mod=Map&act=edit&show='.$map);
                break;
                case 'doDelete':
                    // if deleted map is open, redirect to WUI main page
                    $FHANDLER = new CoreRequestHandler($_POST);
                    $current = $FHANDLER->get('map_current');
                    $map     = $FHANDLER->get('map');
                    if($current == 'undefined' || $current == '' || $current == $map)
                        $url = $this->htmlBase.'/frontend/wui/index.php';
                    else
                        $url = $this->htmlBase.'/frontend/wui/index.php?mod=Map&act=edit&show='.$current;

                    $this->handleResponse('handleResponseDelete', 'doDelete',
                                            $this->CORE->getLang()->getText('The map has been deleted.'),
                                                                $this->CORE->getLang()->getText('The map could not be deleted.'),
                                                              1, $url);
                break;
                case 'createObject':
                    $this->handleResponse('handleResponseCreateObject', 'doCreateObject',
                                            $this->CORE->getLang()->getText('The object has been added.'),
                                                                $this->CORE->getLang()->getText('The object could not be added.'),
                                          1);
                break;
                case 'modifyObject':
                    $refresh = null;
                    $success = null;
                    if(isset($_GET['ref']) && $_GET['ref'] == 1) {
                        $refresh = 1;
                        $success = $this->CORE->getLang()->getText('The object has been modified.');
                    }
                    $sReturn = $this->handleResponse('handleResponseModifyObject', 'doModifyObject',
                                            $success,
                                                                $this->CORE->getLang()->getText('The object could not be modified.'),
                                                                $refresh);
                break;
                case 'deleteObject':
                    $aReturn = $this->handleResponseDeleteObject();

                    if($aReturn !== false) {
                        if($this->doDeleteObject($aReturn)) {
                            $sReturn = json_encode(Array('status' => 'OK', 'message' => ''));
                        } else {
                            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The object could not be deleted.'));
                            $sReturn = '';
                        }
                    } else {
                        new GlobalMessage('ERROR', $this->CORE->getLang()->getText('You entered invalid information.'));
                        $sReturn = '';
                    }
                break;
                case 'addModify':
                    $aOpts = Array('show'     => MATCH_MAP_NAME,
                                   'do'       => MATCH_WUI_ADDMODIFY_DO,
                                   'type'     => MATCH_OBJECTTYPE,
                                   'id'       => MATCH_OBJECTID_EMPTY,
                                   'viewType' => MATCH_VIEW_TYPE_SERVICE_EMPTY,
                                   'x'        => MATCH_COORDS_MULTI_EMPTY,
                                   'y'        => MATCH_COORDS_MULTI_EMPTY,
                                   'clone'    => MATCH_OBJECTID_EMPTY);
                    $aVals = $this->getCustomOptions($aOpts);

                    // Initialize unset optional attributes
                    if(!isset($aVals['x'])) {
                        $aVals['x'] = '';
                    }
                    if(!isset($aVals['y'])) {
                        $aVals['y'] = '';
                    }

                    if(!isset($aVals['id'])) {
                        $aVals['id'] = '';
                    }

                    if(!isset($aVals['viewType'])) {
                        $aVals['viewType'] = '';
                    }

                    if(!isset($aVals['clone'])) {
                        $aVals['clone'] = '';
                    }

                    $VIEW = new WuiViewMapAddModify($this->AUTHENTICATION, $this->AUTHORISATION);
                    $VIEW->setOpts($aVals);
                    $sReturn = json_encode(Array('code' => $VIEW->parse()));
                break;
                case 'manageTmpl':
                    $aOpts = Array('show' => MATCH_MAP_NAME);
                    $aVals = $this->getCustomOptions($aOpts);

                    $VIEW = new WuiViewMapManageTmpl($this->AUTHENTICATION, $this->AUTHORISATION);
                    $VIEW->setOpts($aVals);
                    $sReturn = json_encode(Array('code' => $VIEW->parse()));
                break;
                case 'getTmplOpts':
                    $aOpts = Array('show' => MATCH_MAP_NAME,
                                   'name' => MATCH_STRING_NO_SPACE);
                    $aVals = $this->getCustomOptions($aOpts);

                    // Read map config but don't resolve templates and don't use the cache
                    $MAPCFG = new GlobalMapCfg($this->CORE, $aVals['show']);
                    $MAPCFG->readMapConfig(0, false, false);

                    $aTmp = $MAPCFG->getDefinitions('template');
                    $aTmp = $aTmp[$MAPCFG->getTemplateIdByName($aVals['name'])];
                    unset($aTmp['type']);
                    unset($aTmp['object_id']);

                    $sReturn = json_encode(Array('opts' => $aTmp));
                break;
                case 'doTmplAdd':
                    $this->handleResponse('handleResponseDoTmplAdd', 'doTmplAdd',
                                            $this->CORE->getLang()->getText('The object has been added.'),
                                                                $this->CORE->getLang()->getText('The object could not be added.'),
                                                                1);
                break;
                case 'doTmplModify':
                    $this->handleResponse('handleResponseDoTmplModify', 'doTmplModify',
                                            $this->CORE->getLang()->getText('The object has been modified.'),
                                                                $this->CORE->getLang()->getText('The object could not be modified.'),
                                                                1);
                break;
                case 'doTmplDelete':
                    $this->handleResponse('handleResponseDoTmplDelete', 'doTmplDelete',
                                            $this->CORE->getLang()->getText('The template has been deleted.'),
                                                                $this->CORE->getLang()->getText('The template could not be deleted.'),
                                                                1);
                break;
                case 'doExportMap':
                    $this->handleResponse('handleResponseDoExportMap', 'doExportMap',
                                            $this->CORE->getLang()->getText('The map configuration has been exported.'),
                                                                $this->CORE->getLang()->getText('The map configuration could not be exported.'));
                break;
                case 'doImportMap':
                    if($this->handleResponse('handleResponseDoImportMap', 'doImportMap'))
                        header('Location:'.$_SERVER['HTTP_REFERER']);
                break;
                case 'getObjects':
                    $sReturn = $this->handleResponse('handleResponseGetObjects', 'getObjects');
                break;
            }
        }

        return $sReturn;
    }

    protected function handleResponseDoImportMap() {
        $FHANDLER = new CoreRequestHandler($_FILES);
        $this->verifyValuesSet($FHANDLER, Array('map_file'));
        return Array('map_file' => $FHANDLER->get('map_file'));
    }

    protected function doImportMap($a) {
        if(!is_uploaded_file($a['map_file']['tmp_name']))
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The file could not be uploaded (Error: [ERROR]).',
              Array('ERROR' => $a['map_file']['error'].': '.$this->CORE->getUploadErrorMsg($a['map_file']['error']))));

        $mapName = $a['map_file']['name'];

        if(!preg_match(MATCH_CFG_FILE, $mapName))
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The uploaded file is no map configuration file.'));

        $filePath = $this->CORE->getMainCfg()->getValue('paths', 'mapcfg').$mapName;
        return move_uploaded_file($a['map_file']['tmp_name'], $filePath) && $this->CORE->setPerms($filePath);
    }

    protected function handleResponseDoExportMap() {
        $FHANDLER = new CoreRequestHandler($_POST);
        return Array('map' => $FHANDLER->get('map'));
    }

    protected function doExportMap($a) {
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        return $MAPCFG->exportMap();
    }

    protected function handleResponseGetObjects() {
        $FHANDLER = new CoreRequestHandler($_GET);

        $this->verifyValuesSet($FHANDLER, Array('backendid', 'type'));
        if($FHANDLER->get('type') == 'service')
            $this->verifyValuesSet($FHANDLER, Array('name1'));

        return Array('backendid' => $FHANDLER->get('backendid'),
                     'type'      => $FHANDLER->get('type'),
                     'name1'     => $FHANDLER->get('name1'));
    }

    protected function getObjects($a) {
        // Initialize the backend
        $BACKEND = new CoreBackendMgmt($this->CORE);

        try {
            $BACKEND->checkBackendExists($a['backendid'], true);
            $BACKEND->checkBackendFeature($a['backendid'], 'getObjects', true);
        } catch(BackendConnectionProblem $e) {
            new GlobalMessage('ERROR', $CORE->getLang()->getText('Connection Problem (Backend: [BACKENDID]): [MSG]',
                  																						Array('BACKENDID' => $a['backendid'], 'MSG' => $e->getMessage())));
            exit();
        }

        $name1 = ($a['type'] === 'service' ? $a['name1'] : '');
        $type  = $a['type'];

        // Initialize an empty list
        if($a['type'] !== 'service')
            $aRet = Array(Array('name1' => ''));
        else
            $aRet = Array(Array('name1' => '', 'name2' => ''));

        // Read all objects of the requested type from the backend
        try {
            $objs = $BACKEND->getBackend($a['backendid'])->getObjects($type, $name1, '');
            foreach($objs AS $obj) {
                if($a['type'] !== 'service')
                    $aRet[] = Array('name1' => $obj['name1']);
                else
                    $aRet[] = Array('name1' => $obj['name1'],
                                    'name2' => $obj['name2']);
            }
        } catch(BackendConnectionProblem $e) {}

        return json_encode($aRet);
    }

    protected function doTmplModify($a) {
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        $MAPCFG->readMapConfig(0, false, false);

        $id = $MAPCFG->getTemplateIdByName($a['opts']['name']);

        // set options in the array
        foreach($a['opts'] AS $key => $val) {
            $MAPCFG->setValue('template', $id, $key, $val);
        }

        $MAPCFG->writeElement('template', $id);

        // delete map lock
        if(!$MAPCFG->deleteMapLock()) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('mapLockNotDeleted'));
        }

        return true;
    }

    protected function handleResponseDoTmplModify() {
        $bValid = true;
        // Validate the response

        $FHANDLER = new CoreRequestHandler($_POST);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;
        if($bValid && !$FHANDLER->isSetAndNotEmpty('name'))
            $bValid = false;

        // All fields: Regex check
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && !$FHANDLER->match('name', MATCH_STRING_NO_SPACE))
            $bValid = false;

        // Check if the map exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The map does not exist.'));

            $bValid = false;
        }

        // Check if the template already exists
        // Read map config but don't resolve templates and don't use the cache
        $MAPCFG = new GlobalMapCfg($this->CORE, $FHANDLER->get('map'));
        $MAPCFG->readMapConfig(0, false, false);
        if($bValid && count($MAPCFG->getTemplateNames('/^'.$FHANDLER->get('name').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('A template with this name does not exist.'));

            $bValid = false;
        }
        $MAPCFG = null;

        // FIXME: Recode to FHANDLER
        $aOpts = $_POST;

        // Remove the parameters which are not options of the object
        unset($aOpts['submit']);
        unset($aOpts['map']);

        // Transform the array to key => value form
        $opts = Array('name' => $FHANDLER->get('name'));
        foreach($aOpts AS $key => $a) {
            if(substr($key, 0, 3) === 'opt' && isset($a['name']) && isset($a['value'])) {
                $opts[$a['name']] = $a['value'];
            }
        }

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array('map' => $FHANDLER->get('map'),
                         'opts' => $opts);
        } else {
            return false;
        }
    }

    protected function doTmplDelete($a) {
        // Read map config but don't resolve templates and don't use the cache
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        $MAPCFG->readMapConfig(0, false, false);

        $id = $MAPCFG->getTemplateIdByName($a['name']);

        // first delete element from array
        $MAPCFG->deleteElement($id, true);

        // delete map lock
        if(!$MAPCFG->deleteMapLock()) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('mapLockNotDeleted'));
        }

        return true;
    }

    protected function handleResponseDoTmplDelete() {
        $bValid = true;
        // Validate the response

        $FHANDLER = new CoreRequestHandler($_POST);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;
        if($bValid && !$FHANDLER->isSetAndNotEmpty('name'))
            $bValid = false;

        // All fields: Regex check
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && !$FHANDLER->match('name', MATCH_STRING_NO_SPACE))
            $bValid = false;

        // Check if the map exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The map does not exist.'));

            $bValid = false;
        }

        // Check if the template already exists
        // Read map config but don't resolve templates and don't use the cache
        $MAPCFG = new GlobalMapCfg($this->CORE, $FHANDLER->get('map'));
        $MAPCFG->readMapConfig(0, false, false);
        if($bValid && count($MAPCFG->getTemplateNames('/^'.$FHANDLER->get('name').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The template does not exist.'));

            $bValid = false;
        }
        $MAPCFG = null;

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array('map' => $FHANDLER->get('map'),
                         'name' => $FHANDLER->get('name'));
        } else {
            return false;
        }
    }

    protected function doTmplAdd($a) {
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        $MAPCFG->readMapConfig(0, false, false);

        // append a new object definition to the map configuration
        $MAPCFG->addElement('template', $a['opts'], true);

        // delete map lock
        if(!$MAPCFG->deleteMapLock()) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('mapLockNotDeleted'));
        }

        return true;
    }

    protected function handleResponseDoTmplAdd() {
        $bValid = true;
        // Validate the response

        $FHANDLER = new CoreRequestHandler($_POST);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;
        if($bValid && !$FHANDLER->isSetAndNotEmpty('name'))
            $bValid = false;

        // All fields: Regex check
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && !$FHANDLER->match('name', MATCH_STRING_NO_SPACE))
            $bValid = false;

        // Check if the map exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The map does not exist.'));

            $bValid = false;
        }

        // Check if the template already exists
        // Read map config but don't resolve templates and don't use the cache
        $MAPCFG = new GlobalMapCfg($this->CORE, $FHANDLER->get('map'));
        $MAPCFG->readMapConfig(0, false, false);
        if($bValid && count($MAPCFG->getTemplateNames('/^'.$FHANDLER->get('name').'$/')) > 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('A template with this name already exists.'));

            $bValid = false;
        }
        $MAPCFG = null;

        // FIXME: Recode to FHANDLER
        $aOpts = $_POST;

        // Remove the parameters which are not options of the object
        unset($aOpts['submit']);
        unset($aOpts['map']);
        unset($aOpts['name']);

        // Transform the array to key => value form
        $opts = Array('name' => $FHANDLER->get('name'));
        foreach($aOpts AS $key => $a) {
            if(substr($key, 0, 3) === 'opt' && isset($a['name']) && isset($a['value'])) {
                $opts[$a['name']] = $a['value'];
            }
        }

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array('map' => $FHANDLER->get('map'),
                         'opts' => $opts);
        } else {
            return false;
        }
    }

    protected function doDeleteObject($a) {
        // initialize map and read map config
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        // Ignore map configurations with errors in it.
        // the problems may get resolved by deleting the object
        try {
            $MAPCFG->readMapConfig();
        } catch(MapCfgInvalid $e) {}

        if(!$MAPCFG->objExists($a['id']))
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The object does not exist.'));

        // first delete element from array
        $MAPCFG->deleteElement($a['id'], true);

        // delete map lock
        if(!$MAPCFG->deleteMapLock()) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('mapLockNotDeleted'));
        }

        return true;
    }

    protected function handleResponseDeleteObject() {
        $bValid = true;
        // Validate the response

        $FHANDLER = new CoreRequestHandler($_GET);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;
        if($bValid && !$FHANDLER->isSetAndNotEmpty('id'))
            $bValid = false;

        // All fields: Regex check
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && !$FHANDLER->match('id', MATCH_OBJECTID))
            $bValid = false;

        // Check if the map exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The map does not exist.'));

            $bValid = false;
        }

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array('map' => $FHANDLER->get('map'),
                         'id' => $FHANDLER->get('id'));
        } else {
            return false;
        }
    }

    protected function doModifyObject($a) {
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        try {
            $MAPCFG->readMapConfig();
        } catch(MapCfgInvalid $e) {}

        if(!$MAPCFG->objExists($a['id']))
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The object does not exist.'));

        // set options in the array
        foreach($a['opts'] AS $key => $val) {
            $MAPCFG->setValue($a['id'], $key, $val);
        }

        // write element to file
        $MAPCFG->storeUpdateElement($a['id']);

        // delete map lock
        if(!$MAPCFG->deleteMapLock()) {
            new GlobalMessage('ERROR', $CORE->getLang()->getText('mapLockNotDeleted'));
        }

        return json_encode(Array('status' => 'OK', 'message' => ''));
    }

    protected function handleResponseModifyObject() {
        $bValid = true;
        // Validate the response

        // Need to listen to POST and GET
        $aResponse = array_merge($_GET, $_POST);
        // FIXME: Maybe change all to POST
        $FHANDLER = new CoreRequestHandler($aResponse);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;
        if($bValid && !$FHANDLER->isSetAndNotEmpty('id'))
            $bValid = false;

        // All fields: Regex check
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && $FHANDLER->isSetAndNotEmpty('id') && !$FHANDLER->match('id', MATCH_OBJECTID))
            $bValid = false;

        // Check if the map exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The map does not exist.'));

            $bValid = false;
        }

        // FIXME: Recode to FHANDLER
        $aOpts = $aResponse;
        // Remove the parameters which are not options of the object
        unset($aOpts['act']);
        unset($aOpts['mod']);
        unset($aOpts['map']);
        unset($aOpts['ref']);
        unset($aOpts['id']);
        unset($aOpts['lang']);

        // Also remove all "helper fields" which begin with a _
        foreach($aOpts AS $key => $val) {
            if(strpos($key, '_') === 0) {
                unset($aOpts[$key]);
            }
        }

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array('map'     => $FHANDLER->get('map'),
                         'id'      => $FHANDLER->get('id'),
                         'refresh' => $FHANDLER->get('ref'),
                         'opts'    => $aOpts);
        } else {
            return false;
        }
    }

    protected function doCreateObject($a) {
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        $MAPCFG->readMapConfig();

        // append a new object definition to the map configuration
        $MAPCFG->addElement($a['type'], $a['opts'], true);

        // delete map lock
        if(!$MAPCFG->deleteMapLock()) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('mapLockNotDeleted'));
        }

        return true;
    }

    protected function handleResponseCreateObject() {
        $bValid = true;
        // Validate the response

        $FHANDLER = new CoreRequestHandler($_POST);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;
        if($bValid && !$FHANDLER->isSetAndNotEmpty('type'))
            $bValid = false;

        // All fields: Regex check
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && !$FHANDLER->match('type', MATCH_OBJECTTYPE))
            $bValid = false;

        // Check if the map exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The map does not exist.'));

            $bValid = false;
        }

        // FIXME: Recode to FHANDLER
        $aOpts = $_POST;
        // Remove the parameters which are not options of the object
        unset($aOpts['map']);
        unset($aOpts['type']);

        // Also remove all "helper fields" which begin with a _
        foreach($aOpts AS $key => $val) {
            if(strpos($key, '_') === 0) {
                unset($aOpts[$key]);
            }
        }

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array('map' => $FHANDLER->get('map'),
                         'type' => $FHANDLER->get('type'),
                         'opts' => $aOpts);
        } else {
            return false;
        }
    }

    protected function doDelete($a) {
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        try {
            $MAPCFG->readMapConfig();
        } catch(MapCfgInvalidObject $e) {}
        $MAPCFG->deleteMapConfig();

        return true;
    }

    protected function handleResponseDelete() {
        $bValid = true;
        // Validate the response

        $FHANDLER = new CoreRequestHandler($_POST);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;

        // All fields: Regex check
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;

        // Check if the map exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map').'$/')) <= 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The map does not exist.'));

            $bValid = false;
        }

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array('map' => $FHANDLER->get('map'));
        } else {
            return false;
        }
    }

    protected function doRename($a) {
        $files = Array();

        // loop all map configs to replace mapname in all map configs
        foreach($this->CORE->getAvailableMaps() as $mapName) {
            $MAPCFG1 = new GlobalMapCfg($this->CORE, $mapName);
            $MAPCFG1->readMapConfig();

            $i = 0;
            // loop definitions of type map
            foreach($MAPCFG1->getDefinitions('map') AS $key => $obj) {
                // check if old map name is linked...
                if($obj['map_name'] == $a['map']) {
                    $MAPCFG1->setValue('map', $i, 'map_name', $a['map_new_name']);
                    $MAPCFG1->writeElement('map',$i);
                }
                $i++;
            }
        }

        // rename config file
        rename($this->CORE->getMainCfg()->getValue('paths', 'mapcfg').$a['map'].'.cfg',
               $this->CORE->getMainCfg()->getValue('paths', 'mapcfg').$a['map_new_name'].'.cfg');

        return true;
    }

    protected function handleResponseRename() {
        $bValid = true;
        // Validate the response

        $FHANDLER = new CoreRequestHandler($_POST);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map_new_name'))
            $bValid = false;

        // All fields: Regex check
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && !$FHANDLER->match('map_new_name', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && $FHANDLER->isSetAndNotEmpty('map_current') && !$FHANDLER->match('map_current', MATCH_MAP_NAME))
            $bValid = false;

        // Check if the new map already exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map_new_name').'$/')) > 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The new mapname does already exist.'));

            $bValid = false;
        }

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array(
                       'map_new_name' => $FHANDLER->get('map_new_name'),
                       'map' => $FHANDLER->get('map'),
                       'map_current' => $FHANDLER->get('map_current'));
        } else {
            return false;
        }
    }

    protected function doAdd($a) {
        $MAPCFG = new GlobalMapCfg($this->CORE, $a['map']);
        if(!$MAPCFG->createMapConfig())
            return false;

        $MAPCFG->addElement('global', $a, true);
        return true;
    }

    protected function handleResponseAdd() {
        $bValid = true;
        // Validate the response

        $FHANDLER = new CoreRequestHandler($_POST);

        // Check for needed params
        if($bValid && !$FHANDLER->isSetAndNotEmpty('map'))
            $bValid = false;

        // Check for valid vars
        if($bValid && !$FHANDLER->match('map', MATCH_MAP_NAME))
            $bValid = false;
        if($bValid && $FHANDLER->isSetAndNotEmpty('map_iconset') && !$FHANDLER->match('map_iconset', MATCH_STRING_NO_SPACE))
            $bValid = false;
        if($bValid && $FHANDLER->isSetAndNotEmpty('map_image') && !$FHANDLER->match('map_image', MATCH_PNG_GIF_JPG_FILE_OR_URL_NONE))
            $bValid = false;

        // Check if the map already exists
        if($bValid && count($this->CORE->getAvailableMaps('/^'.$FHANDLER->get('map').'$/')) > 0) {
            new GlobalMessage('ERROR', $this->CORE->getLang()->getText('The mapname does already exist.'));

            $bValid = false;
        }

        // Store response data
        if($bValid === true) {
            // Return the data
            return Array(
                       'map' => $FHANDLER->get('map'),
                       'iconset' => $FHANDLER->get('map_iconset'),
                       'map_image' => $FHANDLER->get('map_image'));
        } else {
            return false;
        }
    }

    private function getMapProperties() {
        $MAPCFG = new NagVisMapCfg($this->CORE, $this->name);
        $MAPCFG->readMapConfig(ONLY_GLOBAL);

        $arr = Array();
        $arr['map_name']                 = $MAPCFG->getName();
        $arr['alias']                    = $MAPCFG->getValue(0, 'alias');
        $arr['background_image']         = $MAPCFG->BACKGROUND->getFile();
        $arr['background_color']         = $MAPCFG->getValue(0, 'background_color');
        $arr['favicon_image']            = $this->CORE->getMainCfg()->getValue('paths', 'htmlimages').'internal/favicon.png';
        $arr['page_title']               = $MAPCFG->getValue(0, 'alias').' ([SUMMARY_STATE]) :: '.$this->CORE->getMainCfg()->getValue('internal', 'title');
        $arr['event_background']         = $MAPCFG->getValue(0, 'event_background');
        $arr['event_highlight']          = $MAPCFG->getValue(0, 'event_highlight');
        $arr['event_highlight_interval'] = $MAPCFG->getValue(0, 'event_highlight_interval');
        $arr['event_highlight_duration'] = $MAPCFG->getValue(0, 'event_highlight_duration');
        $arr['event_log']                = $MAPCFG->getValue(0, 'event_log');
        $arr['event_log_level']          = $MAPCFG->getValue(0, 'event_log_level');
        $arr['event_log_events']         = $MAPCFG->getValue(0, 'event_log_events');
        $arr['event_log_height']         = $MAPCFG->getValue(0, 'event_log_height');
        $arr['event_log_hidden']         = $MAPCFG->getValue(0, 'event_log_hidden');
        $arr['event_scroll']             = $MAPCFG->getValue(0, 'event_scroll');
        $arr['event_sound']              = $MAPCFG->getValue(0, 'event_sound');
        $arr['in_maintenance']           = $MAPCFG->getValue(0, 'in_maintenance');

        return json_encode($arr);
    }

    private function getMapObjects() {
        // Initialize backends
        $BACKEND = new CoreBackendMgmt($this->CORE);

        $MAPCFG = new NagVisMapCfg($this->CORE, $this->name);
        $MAPCFG->readMapConfig();

        $MAP = new NagVisMap($this->CORE, $MAPCFG, $BACKEND, GET_STATE, IS_VIEW);
        return $MAP->parseObjectsJson();
    }

    private function getObjectStates() {
        $aOpts = Array('ty' => MATCH_GET_OBJECT_TYPE,
                       'i'  => MATCH_STRING_NO_SPACE_EMPTY);
        $aVals = $this->getCustomOptions($aOpts);

        // Initialize backends
        $BACKEND = new CoreBackendMgmt($this->CORE);

        // Initialize map configuration (Needed in getMapObjConf)
        $MAPCFG = new NagVisMapCfg($this->CORE, $this->name);
        $MAPCFG->readMapConfig();

        // i might not be set when all map objects should be fetched or when only
        // the summary of the map is called
        if($aVals['i'] != '')
            $MAPCFG->filterMapObjects($aVals['i']);

        $MAP = new NagVisMap($this->CORE, $MAPCFG, $BACKEND, GET_STATE, IS_VIEW);
        return $MAP->parseObjectsJson($aVals['ty']);
    }
}
?>