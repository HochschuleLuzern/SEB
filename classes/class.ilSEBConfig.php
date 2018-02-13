<?php
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
    
    public function checkObjectKey($key, $url, $ref_id) {
        if (!$this->conf['allow_object_keys']) {
            return false;
        } else {
            $keys = $this->getObjectKeys($ref_id);
            if ($keys['seb_key_win'] != '' || $keys['seb_key_macos'] != '') {
                return $this->checkKeys($key, $keys, $url);
            } else {
                return false;
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
    
    public function saveSEBConf($conf) {
        $valid_names = array(
            'seb_keys',
            'allow_object_keys',
            'role_deny',
            'role_kiosk'
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
    
    private function checkKeys($key, $keys, $url) {
        foreach ($keys as $seb_key) {
            if ($key == hash('sha256',$url . trim($seb_key))) {
                return true;
            }
        }
        
        return false;
    }
}

