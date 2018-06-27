<?php
/**
 * Copyright (c) 2017 Hochschule Luzern
 *
 * This file is part of the SEB-Plugin for ILIAS.
 
 * SEB-Plugin for ILIAS is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 
 * SEB-Plugin for ILIAS is distributed in the hope that
 * it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 
 * You should have received a copy of the GNU General Public License
 * along with SEB-Plugin for ILIAS.  If not,
 * see <http://www.gnu.org/licenses/>.
 *
 * The SEB-Plugin for ILIAS is a refactoring of a previous Plugin by Stefan
 * Schneider that can be found on Github
 * <https://github.com/hrz-unimr/Ilias.SEBPlugin>
 */

class ilSEBConfig {    
    private static $instance;
    private $conf;
    private $db;
    
    public function checkSebKey($key, $url) {
        return $this->checkKeys($key, $this->conf['seb_keys'], $url);
    }
    
    public function getSebKeysString() {
        return implode(",", $this->conf['seb_keys']);
    }
    
    public function getRoleDeny() {
        return $this->conf['role_deny'];
    }
    
    public function getRoleKiosk() {
        return $this->conf['role_kiosk'];
    }
    
    public function getAllowObjectKeys() {
        return $this->conf['allow_object_keys'];
    }
    
    public function getActivateSessionControl() {
    	return $this->conf['activate_session_control'];
    }
    
    public function getShowPaxPic() {
    	return $this->conf['show_pax_pic'];
    }
    
    /**
     * Returns the seb keys for an object reference id
     * 
     * @param integer $ref_id integer object reference id
     * @return array of valid keys for current object reference. Array values can contain a comma separated list of keys!
     */
    public function getObjectKeys($ref_id) {
        $q = $this->db->query("SELECT * FROM ui_uihk_seb_keys where ref_id=".$this->db->quote($ref_id, 'integer'));
        if ($keys = $this->db->fetchAssoc($q)) {
            return $keys;
        } else {
            return array(
                'seb_key_win' => '',
                'seb_key_macos' => ''
            );
        }
    }
    
    /**
     * Check a key against the object specific keys of a specific object reference
     * 
     * @param string $key SHA256 hash of key and url as provided by SEB in the corresponding header
     * @param string $url Full Url of the request
     * @param integer $ref_id Object Reference of the object against which to check
     * @return boolean true if it is a valid object key
     */
    public function checkObjectKey($key, $url, $ref_id) {
        if (!$this->conf['allow_object_keys']) {
            return false;
        } else {
            $keys = $this->getObjectKeys($ref_id);
            if ($keys['seb_key_win'] != '' || $keys['seb_key_macos'] != '') {
                $keys_merged = array_merge(explode(',', $keys['seb_key_win']), explode(',', $keys['seb_key_macos']));
                return $this->checkKeys($key, $keys_merged, $url);
            } else {
                return false;
            }
            
        }
    }
    
    /**
     * Check a key against all the keys of all objects needed for requests where object reference is unknown
     *
     * @param string $key SHA256 hash of key and url as provided by SEB in the corresponding header
     * @param string $url Full Url of the request
     * @return boolean true if it is a valid object key
     */
    public function checkKeyAgainstAllObjectKeys($key, $url) {
        if (!$this->conf['allow_object_keys']) {
            return false;
        } else {
            $q = $this->db->query('SELECT seb_key_win, seb_key_macos FROM ui_uihk_seb_keys');
            $result = $this->db->fetchAll($q);
            $keys = [];
            
            foreach ($result as $result_key) {
                $keys = array_merge($keys, explode(',', $result_key['seb_key_win']), explode(',', $result_key['seb_key_macos']));
            }

            if ($keys == []) {
                return false;
            } else {
                return $this->checkKeys($key, $keys, $url);
            }
            
        }
    }
    
    private function __construct() {
        global $DIC;
        $this->db = $DIC->database();
        if ($this->db->tableExists('ui_uihk_seb_conf')) {
            $this->readSEBConf();
        }
    }
    
    public static function getInstance() {
        if (! isset(self::$instance)) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Safes all information from the config form.
     * 
     * @param array $conf An associative array containing all configuration values. See the varliable $valid_names for the currently supported keys/configuration options.
     * @return number of updated config values.
     */
    public function saveSEBConf($conf) {
        $valid_names = array(
            'seb_keys',
            'allow_object_keys',
            'role_deny',
            'role_kiosk',
        	'activate_session_control',
        	'show_pax_pic'
        );
        
        $r = 0;
        
        foreach ($conf as $name => $value) {
            if (in_array($name, $valid_names)) {
                if($this->db->update(
                        'ui_uihk_seb_conf', array(
                            'value' => array('text', $value)
                        ),
                        array(
                            'name' => array('text', $name)
                        )
                    ) > 0) {
                        $r += 1;
                 }
                
            } else {
                return -1;
            }
        }
        $this->readSEBConf();
        return $r;
    }
    
    /**
     * Save Object Keys from Settings-Tab in the test
     * 
     * @param integer $ref_id Object reference id of the object the keys are for
     * @param string $seb_key_win Comma separated string of hashes for SEB
     * @param string $seb_key_macos Comma separated string of hashes for SEB
     * @return integer 1 if operation was successful
     */
    public function saveObjectKeys($ref_id, $seb_key_win, $seb_key_macos) {
        $q = $this->db->query("SELECT * FROM ui_uihk_seb_keys where ref_id=".$this->db->quote($ref_id, 'integer'));
        if ($this->db->fetchAssoc($q)) {
            if ($this->db->update('ui_uihk_seb_keys', 
                array('seb_key_win' => array('text', $seb_key_win),
                    'seb_key_macos' => array('text', $seb_key_macos)
                ), 
                array(
                    'ref_id' => array('integer', $ref_id)
                )) > 0) {
                return 1;
            } else {
                return 0;
            }
        } else {
            if ($this->db->insert('ui_uihk_seb_keys',
                array('ref_id' => array('integer', $ref_id),
                    'seb_key_win' => array('text', $seb_key_win),
                    'seb_key_macos' => array('text', $seb_key_macos))) > 0) {
                return 1;
            } else {
                return 0;
            }
        }
    }
    
    /**
     * Read the config for this plugin and store it in this config object
     */
    private function readSEBConf() {
        $q = $this->db->query("SELECT * FROM ui_uihk_seb_conf");
        
        while ($row = $this->db->fetchAssoc($q)) {
            $this->conf[$row['name']] = $row['value'];
        }
        
        $seb_keys = explode(',',$this->conf['seb_keys']);
        
        $this->conf['seb_keys'] = array();
        
        foreach ($seb_keys as $seb_key) {
            $this->conf['seb_keys'][] = trim($seb_key);
        }
    }
    
    /**
     * Check a key given in the SEB-Header by the browser against an array of keys given to this function
     * 
     * @param string $key to be checked (needle)
     * @param string $keys to check against (haystack)
     * @param string $url the request was sent to. Needed to calculate the hashed key to compare
     * @return boolean True if key to check corresponds to a key in the array.
     */
    private function checkKeys($key, $keys, $url) {
        foreach ($keys as $seb_key) {
            if ($key == hash('sha256',$url . trim($seb_key))) {
                return true;
            }
        }
        
        return false;
    }
}

