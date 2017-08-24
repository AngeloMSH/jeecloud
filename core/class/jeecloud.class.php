<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

/**
 *
 */
class jeecloud extends eqLogic {

    /**
     * Function called every minute by Jeedom
     */
    //public static function cron() {
    //}

    /**
     * Function called every 5 minutes by Jeedom
     */
    //public static function cron5() {
    //}

    /**
     * Function called every hour by Jeedom
     */
    //public static function cronHourly() {

    //}

    /**
     * Fonction exécutée automatiquement tous les jours par Jeedom
     */
    //public static function cronDayly() {

    //}

    /**
     *
     */
    public static function listDevicesParameters($_device = '') {
        $return = array();
        $filename = dirname(__FILE__) . '/../config/devices/devices.json';
        try {
            $content = file_get_contents($filename);

        if (is_json($content)) {
                $return += json_decode($content, true)[devices];
            }
        } catch (Exception $e) {

        }
        if (isset($_device) && $_device != '') {
            if (isset($return[$_device])) {
                return $return[$_device];
            }
            return array();
        }
        return $return;
    }

     /**
      * Process ping received from cloud
      */
    public static function gotPing($ts) {
        log::add('jeecloud', 'info', 'Got ping at timestamp: '. $ts);
    }

    /**
      * Process action received from cloud
      */
    public static function gotAction($ts, $sdid, $ddid, $data) {
        log::add('jeecloud', 'info', 'Got action at timestamp: '. $ts . ' from ' . $sdid . ' to '. $ddid);
        $jeecloud = jeecloud::byLogicalId($ddid, 'jeecloud');
        log::add('jeecloud', 'info', 'Got action for: '. print_r($jeecloud, true));
        foreach ($data['actions'] as $action) {
            log::add('jeecloud', 'info', 'Execute action: '. $action['name']);
            switch ($action['name']) {
                case "setOn":
                    $jeecloud->setOn();
                    break;
                case "setOff":
                    $jeecloud->setOff();
                    break;
                case "toggle":
                    $jeecloud->toggle();
                    break;
                default :
                    break;
            } 
        }
    }

    /**
     * This function starts the nodejs daemon
     */
    public static function deamon_start() {
        self::deamon_stop();
        log::add('jeecloud', 'info', 'Starting Jeecloud service');
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Please check setup', __FILE__));
        }
        // url between node and jeedom to get return from cloud
        //$url = network::getNetworkAccess('internal') . '/plugins/jeecloud/core/api/jeecloud.php?apikey=' . jeedom::getApiKey('jeecloud');
        $url = 'http://127.0.0.1/plugins/jeecloud/core/api/jeecloud.php?apikey=' . jeedom::getApiKey('jeecloud');
        if (config::byKey('client_id', 'jeecloud') != '' && config::byKey('client_secret', 'jeecloud') != '') {
            log::add('jeecloud','info','launching daemon ....');
            jeecloud::launch_service($url);
        }
    }

    /**
     * This function launch the nodejs daemon
     * @return boolean true if ok
     */
    public static function launch_service($url) {
        $log = log::convertLogLevel(log::getLogLevel('jeecloud'));
        $jeecloudNode_path = realpath(dirname(__FILE__) . '/../../node');
        $cmd = 'nice -n 19 nodejs ' . $jeecloudNode_path . '/jeecloud.js ' . $url . ' ' . $log;
        log::add('jeecloud', 'debug', 'Lauching Jeecloud Daemon : ' . $cmd);
        $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('jeecloud_node') . ' 2>&1 &');
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            log::add('jeecloud', 'error', $result);
            return false;
        }
        // trying to start daemon
        $i = 0;
        while ($i < 30) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i++;
        }
        if ($i >= 30) {
            log::add('jeecloud', 'error', 'Can\'t start Jeecloud Daemon, check setup', 'unableStartDeamon');
            return false;
        }
        message::removeAll('jeecloud', 'unableStartDeamon');
        log::add('jeecloud', 'info', 'Jeecloud Daemon launched');
        return true;
    }

    /**
     * Function to stop the Node Daemon.
     */
    public static function deamon_stop() {
        exec('kill $(ps aux | grep "jeecloud/node/jeecloud.js" | awk \'{print $2}\')');
        log::add('jeecloud', 'info', 'Stopping Jeecloud service');
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            sleep(1);
            exec('kill -9 $(ps aux | grep "jeecloud/node/jeecloud.js" | awk \'{print $2}\')');
        }
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            sleep(1);
            exec('sudo kill -9 $(ps aux | grep "jeecloud/node/jeecloud.js" | awk \'{print $2}\')');
        }
        config::save('jeecloud', '0',  'jeecloud');
    }

    /**
     * Return info on running state of the daemon.
     * @return array
     */
    public static function deamon_info() {
        $return = array();
        $return['log'] = 'jeecloud_node';
        $return['state'] = 'nok';
        // looking for a running server
        $pid = trim( shell_exec ('ps ax | grep "jeecloud/node/jeecloud.js" | grep -v "grep" | wc -l') );
        if ($pid != '' && $pid != '0') {
            $return['state'] = 'ok';
        }
        $return['launchable'] = 'ok';
        if ((config::byKey('client_id', 'jeecloud') == 'none' || config::byKey('client_id', 'jeecloud') == '') && (config::byKey('client_secret','jeecloud') == '')) {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('jeecloud not configured', __FILE__);
        }
        return $return;
    }

    /**
     * Check dependancy info for needed node modules.
     * @return boolean true if ok
     */
    public static function dependancy_info() {
        $return = array();
        $return['log'] = 'jeecloud_dep';
        $ws = realpath(dirname(__FILE__) . '/../../node/node_modules/ws');
        $http = realpath(dirname(__FILE__) . '/../../node/node_modules/http');
        $return['progress_file'] = '/tmp/jeecloud_dep';
        if (is_dir($http) && is_dir($ws)) {
        $return['state'] = 'ok';
        } else {
            $return['state'] = 'nok';
        }
        return $return;
    }

    /**
     * To install dependancy of the module.
     */
    public static function dependancy_install() {
        log::add('jeecloud','info','Installation des dépendances nodejs');
        $resource_path = realpath(dirname(__FILE__) . '/../../resources');
        passthru('/bin/bash ' . $resource_path . '/nodejs.sh ' . $resource_path . ' > ' . log::getPathToLog('jeecloud_dep') . ' 2>&1 &');
    }

    /**
     * Send command to nodejs server.
     */
    public static function sendCommand($ddid, $data) {
        $ip = '127.0.0.1';
        $port = '8099';
        $msg = '{"device_id":"' . $ddid . '", "data":' . $data . '}';
        log::add('jeecloud', 'info', 'send command: ' . $msg);
        $fp = fsockopen($ip, $port, $errstr);
        if (!$fp) {
            log::add("ERROR: $errstr");
        } else {
            fwrite($fp, $msg);
            fclose($fp);
        }
    }

    /**
     * Toggle state
     */
    public function toggle() {
        //toggle value
        $jeecloudCmd = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(),'jeecloud:state');
        $newState = $jeecloudCmd->getConfiguration('value')?false:true;
        $jeecloudCmd->setConfiguration('value', $newState);
        $jeecloudCmd->save();
        $data = '{"state": "'.($newState?"on":"off").'"}';
        $result = jeecloud::sendCommand($this->getConfiguration('device_id'), $data);
        $jeecloudCmd->event($newState);
        log::add('jeecloud', 'debug', 'newState : ' . ($newState?"true":"false"));
    }

    /**
     * setOn State
     */
    public function setOn() {
        //set On value
        $jeecloudCmd = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(),'jeecloud:state');
        $jeecloudCmd->setConfiguration('value', true);
        $jeecloudCmd->save();
        $data = '{"state": "on"}';
        $result = jeecloud::sendCommand($this->getConfiguration('device_id'), $data);
        $jeecloudCmd->event(true);
        log::add('jeecloud', 'debug', 'newState : true');
    }

    /**
     * setOff State
     */
    public function setOff() {
        //set Off value
        $jeecloudCmd = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(),'jeecloud:state');
        $jeecloudCmd->setConfiguration('value', false);
        $jeecloudCmd->save();
        $data = '{"state": "off"}';
        $result = jeecloud::sendCommand($this->getConfiguration('device_id'), $data);
        $jeecloudCmd->event(false);
        log::add('jeecloud', 'debug', 'newState : false');
    }

    /**
     * Get state
     * @return boolean state
     */
    public function getState() {
        //retrieve value
        $jeecloudCmd = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(),'jeecloud:state');
        $state = $jeecloudCmd->getConfiguration('value')?true:false;
        log::add('jeecloud', 'debug', 'State : ' . ($state?"true":"false"));
        return $state;
    }

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    public function postSave() {
        
    }

    public function preUpdate() {
        if (empty($this->getConfiguration('device_id')) && empty($this->getConfiguration('device_token'))) {
            throw new Exception(__('Empty fields',__FILE__));
        }
        $this->setLogicalId($this->getConfiguration('device_id'));
    }

    /**
     * Execute actions after updating an object
     */
    public function postUpdate() {
        log::add('jeecloud', 'debug', '** postUpdate **');
        $this->setLogicalId('abcd');
        // switch toggle
        if ($this->getConfiguration('model')==0) {
            $cmdlogic = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(), 'jeecloud:state');
            if (!is_object($cmdlogic)) {
                $jeecloudCmd = new jeecloudCmd();
                $jeecloudCmd->setName(__('state', __FILE__));
                $jeecloudCmd->setEqLogic_id($this->id);
                $jeecloudCmd->setEqType('jeecloud');
                $jeecloudCmd->setLogicalId('jeecloud:state');
                $jeecloudCmd->setType('info');
                $jeecloudCmd->setSubType('binary');
                $jeecloudCmd->setIsHistorized(0);
                $jeecloudCmd->save();
            }

            $cmdlogic = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(), 'jeecloud:toggle');
            if (!is_object($cmdlogic)) {
                $jeecloudCmd = new jeecloudCmd();
                $jeecloudCmd->setName(__('Toggle', __FILE__));
                $jeecloudCmd->setEqLogic_id($this->id);
                $jeecloudCmd->setEqType('jeecloud');
                $jeecloudCmd->setConfiguration('request', 'state');
                $jeecloudCmd->setLogicalId('jeecloud:toggle');
                $jeecloudCmd->setType('action');
                $jeecloudCmd->setSubType('other');
                $jeecloudCmd->setIsHistorized(0);
                $jeecloudCmd->save();
            }
        }
        // Relay
        if ($this->getConfiguration('model')==1) {
            $cmdlogic = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(), 'jeecloud:state');
            if (!is_object($cmdlogic)) {
                $jeecloudCmd = new jeecloudCmd();
                $jeecloudCmd->setName(__('state', __FILE__));
                $jeecloudCmd->setEqLogic_id($this->id);
                $jeecloudCmd->setEqType('jeecloud');
                $jeecloudCmd->setLogicalId('jeecloud:state');
                $jeecloudCmd->setType('info');
                $jeecloudCmd->setSubType('binary');
                $jeecloudCmd->setIsVisible(1);
                $jeecloudCmd->setIsHistorized(0);
                $jeecloudCmd->save();
            }
        }
        // Temperature
        if ($this->getConfiguration('model')==2) {
            $cmdlogic = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(), 'jeecloud:temp');
            if (!is_object($cmdlogic)) {
                $jeecloudCmd = new jeecloudCmd();
                $jeecloudCmd->setName(__('Temperature', __FILE__));
                $jeecloudCmd->setEqLogic_id($this->id);
                $jeecloudCmd->setEqType('jeecloud');
                $jeecloudCmd->setLogicalId('jeecloud:temp');
                $jeecloudCmd->setType('info');
                $jeecloudCmd->setSubType('numeric');
                $jeecloudCmd->setIsVisible(1);
                $jeecloudCmd->setIsHistorized(0);
                $jeecloudCmd->save();
            }
        }

        $cmdlogic = jeecloudCmd::byEqLogicIdAndLogicalId($this->getId(), 'jeecloud:message');
         if (!is_object($cmdlogic)) {
            $jeecloudCmd = new jeecloudCmd();
            $jeecloudCmd->setName(__('Message', __FILE__));
            $jeecloudCmd->setEqLogic_id($this->id);
            $jeecloudCmd->setEqType('jeecloud');
            $jeecloudCmd->setLogicalId('jeecloud:message');
            $jeecloudCmd->setType('action');
            $jeecloudCmd->setSubType('message');
            $jeecloudCmd->setIsVisible(0);
            $jeecloudCmd->setIsHistorized(0);
            $jeecloudCmd->save();
        }

        $devices_filename = realpath(dirname(__FILE__) . '/../../resources/devices.json');
        log::add('jeecloud', 'debug', 'resources file for devices: ' . $devices_filename);
        // TODO update file with all cmdlogic (device_id and device_token)
    }

    public function preRemove() {

    }

    public function postRemove() {

    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
     */
    //public function toHtml($_version = 'dashboard') {

    //}

}

class jeecloudCmd extends cmd {
    
    public function dontRemoveCmd() {
		if ($this->getLogicalId() == 'refresh') {
			return true;
		}
		return false;
	}
    
    public function preSave() {
        if ($this->getLogicalId() == 'refresh') {
            return;
        }
        if ($this->getConfiguration('virtualAction') == 1) {
            $actionInfo = jeecloudCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getName());
			if (is_object($actionInfo)) {
				$this->setId($actionInfo->getId());
			}
		    if ($this->getType() == 'action') {
			    if ($this->getConfiguration('infoName') == '') {
				    throw new Exception(__('Le nom de la commande info ne peut etre vide', __FILE__));
			    }
			    $cmd = cmd::byId(str_replace('#', '', $this->getConfiguration('infoName')));
			    if (is_object($cmd)) {
				    $this->setSubType($cmd->getSubType());
			    } else {
				    $actionInfo = jeecloudCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getConfiguration('infoName'));
				    if (!is_object($actionInfo)) {
					    $actionInfo = new jeecloudCmd();
					    $actionInfo->setType('info');
					    switch ($this->getSubType()) {
						    case 'slider':
							    $actionInfo->setSubType('numeric');
							    break;
						    default:
							    $actionInfo->setSubType('string');
							    break;
					    }
				    }
				    $actionInfo->setConfiguration('virtualAction', 1);
				    $actionInfo->setName($this->getConfiguration('infoName'));
				    $actionInfo->setEqLogic_id($this->getEqLogic_id());
				    $actionInfo->save();
				    $this->setConfiguration('infoId', $actionInfo->getId());
			    }
		    } else {
			    $calcul = $this->getConfiguration('calcul');
			    if (strpos($calcul, '#' . $this->getId() . '#') !== false) {
				    throw new Exception(__('Vous ne pouvez faire un calcul sur la valeur elle meme (boucle infinie)!!!', __FILE__));
                }
			    preg_match_all("/#([0-9]*)#/", $calcul, $matches);
			    $value = '';
			    foreach ($matches[1] as $cmd_id) {
				    if (is_numeric($cmd_id)) {
					    $cmd = self::byId($cmd_id);
					    if (is_object($cmd) && $cmd->getType() == 'info') {
						    $value .= '#' . $cmd_id . '#';
					    }
				    }
			    }
			    preg_match_all("/variable\((.*?)\)/", $calcul, $matches);
			    foreach ($matches[1] as $variable) {
				    $value .= '#variable(' . $variable . ')#';
			    }
			    if ($value != '') {
				    $this->setValue($value);
			    }
		    }
        }
    }
    
    public function postSave() {
        if ($this->getType() == 'info' && $this->getConfiguration('virtualAction', 0) == '0' && $this->getConfiguration('calcul') != '') {
            $this->event($this->execute());
        }
    }

    public function execute($_options = array()) {
        log::add('jeecloud', 'debug', 'Execute cmd: '. $this->getName(). ' ' . $this->getType(). ' ' . $this->getSubType() . ' '. ($this->getConfiguration('virtualAction')?"virtual":"physic"));

        switch ($this->getType()) {
            case 'info' :
                if ($this->getConfiguration('virtualAction') == '1') {
                    try {
                        $result = jeedom::evaluateExpression($this->getConfiguration('calcul'));
                        switch ($this->getSubType()) {
                            case 'numeric':
                                if (is_numeric($result)) {
                                    $result = number_format($result, 2);
                                } else {
                                    $result = str_replace('"', '', $result);
                                }
                                if (strpos($result, '.') !== false) {
                                    $result = str_replace(',', '', $result);
                                } else {
                                    $result = str_replace(',', '.', $result);
                                }
                                $data = '{"'.$this->getName().'": "' . $result . '"}';
                                break;
                            case 'binary':
                                $data = '{"'.$this->getName().'": "'. ($result?"on":"off") .'"}';
                                break;
                            case 'other':
                                $data = '{"'.$this->getName().'": "'. $result .'"}';
                                break;
                        }
                        log::add('jeecloud', 'debug', 'retour info virtual: ' . $result);
                    } catch (Exception $e) {
                        log::add('jeecloud', 'info', $e->getMessage());
                        return null;
                    }
                    $send = jeecloud::sendCommand($this->getEqLogic()->getConfiguration('device_id'), $data);
                    return $result;
                }
                else
                {
                    return $this->getValue();
                }
                break;
            case 'action' :
                if ($this->getConfiguration('virtualAction') == '1') {
                    $jeecloudVirtualCmd = jeecloudCmd::byId($this->getConfiguration('infoId'));
                }
                else
                {
                    $eqLogic = $this->getEqLogic();
                    $request = $this->getConfiguration('request');
                    log::add('jeecloud', 'debug', 'request: ' . $request);
                    switch ($this->getSubType()) {
                        case 'slider':
                            $request = str_replace('#slider#', $_options['slider'], $request);
                            break;
                        case 'color':
                            $request = str_replace('#color#', $_options['color'], $request);
                            break;
                        case 'message':
                            if ($_options != null)  {
                                $replace = array('#title#', '#message#');
                                $replaceBy = array($_options['title'], $_options['message']);
                                if ( $_options['title'] == '') {
                                    throw new Exception(__('Le sujet ne peuvent être vide', __FILE__));
                                }
                                $request = str_replace($replace, $replaceBy, $request);
                            }
                            else
                            {
                                $request = 1;
                            }
                            break;
                        default : $request == null ?  1 : !($request);
                    }
                    if ($eqLogic->getConfiguration('device_id', '') == '') {
                        log::add('jeecloud', 'error', 'Empty device_id');
                        return true;
                    }

                    switch ($this->getname()) {
                        case 'Toggle':
                            $eqLogic->toggle();
                            break;
                        case 'setOn':
                            $eqLogic->seton();
                            break;
                        case 'setOff':
                            $eqLogic->setoff();
                            break;
                        default:
                    }

                    return $request;
                }
        }
        return true;
    }

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

}
