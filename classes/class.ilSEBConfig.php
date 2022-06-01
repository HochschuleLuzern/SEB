<?php  declare(strict_types=1);
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

class ilSEBConfig
{
    private $conf;
    private $db;
    
    public function __construct(ilDBInterface $db)
    {
        $this->db = $db;
        if ($this->db->tableExists('ui_uihk_seb_conf')) {
            $this->readSEBConf();
        }
    }
    
    public function checkSebKey(string $key_from_browser, string $request_url) : bool
    {
        return $this->checkKeys($key_from_browser, $this->conf['seb_keys'], $request_url);
    }
    
    public function getSebKeysString() : string
    {
        return implode(",", $this->conf['seb_keys']);
    }
    
    public function getRoleDeny() : int
    {
        return (int) $this->conf['role_deny'];
    }
    
    public function getRoleKiosk() : string
    {
        return $this->conf['role_kiosk'];
    }
    
    public function getAllowObjectKeys() : bool
    {
        return (bool) $this->conf['allow_object_keys'];
    }
    
    public function getActivateSessionControl() : bool
    {
        return (bool) $this->conf['activate_session_control'];
    }
    
    public function getShowPaxPic() : bool
    {
        return (bool) $this->conf['show_pax_pic'];
    }
    
    public function getShowPaxMatriculation() : bool
    {
        return (bool) $this->conf['show_pax_matriculation'];
    }
    
    public function getShowPaxUsername() : bool
    {
        return (bool) $this->conf['show_pax_username'];
    }
    
    /**
     * @return string[]
     */
    public function getObjectKeys(int $ref_id) : array
    {
        $q = $this->db->query("SELECT * FROM ui_uihk_seb_keys where ref_id=" . $this->db->quote($ref_id, 'integer'));
        if ($keys = $this->db->fetchAssoc($q)) {
            return $keys;
        } else {
            return array(
                'seb_key_win' => '',
                'seb_key_macos' => ''
            );
        }
    }
    
    public function checkObjectKey(string $key, string $url, ?int $ref_id) : bool
    {
        if ($ref_id === null || !$this->conf['allow_object_keys']) {
            return false;
        }
        
        $keys = $this->getObjectKeys($ref_id);
        if ($keys['seb_key_win'] != '' || $keys['seb_key_macos'] != '') {
            $keys_merged = array_merge(explode(',', $keys['seb_key_win']), explode(',', $keys['seb_key_macos']));
            return $this->checkKeys($key, $keys_merged, $url);
        }
        
        return false;
    }
    
    public function checkKeyAgainstAllObjectKeys(string $key, string $url) : bool
    {
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
    
    /**
     * Safes all information from the config form.
     *
     * @param array $conf An associative array containing all configuration values. See the varliable $valid_names for the currently supported keys/configuration options.
     */
    public function saveSEBConf(array $conf) : int
    {
        $valid_names = array(
            'seb_keys',
            'allow_object_keys',
            'role_deny',
            'role_kiosk',
            'activate_session_control',
            'show_pax_pic',
            'show_pax_matriculation',
            'show_pax_username'
        );
        
        $r = 0;
        
        foreach ($conf as $name => $value) {
            if (in_array($name, $valid_names)) {
                if ($this->db->update(
                    'ui_uihk_seb_conf',
                    array(
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
    
    public function saveObjectKeys(int $ref_id, string $seb_key_win, string $seb_key_macos) : bool
    {
        $q = $this->db->query("SELECT * FROM ui_uihk_seb_keys where ref_id=" . $this->db->quote($ref_id, 'integer'));
        if ($this->db->fetchAssoc($q)) {
            if ($this->db->update(
                'ui_uihk_seb_keys',
                array('seb_key_win' => array('text', $seb_key_win),
                    'seb_key_macos' => array('text', $seb_key_macos)
                ),
                array(
                    'ref_id' => array('integer', $ref_id)
                )
            ) > 0) {
                return true;
            } else {
                return false;
            }
        } else {
            if ($this->db->insert(
                'ui_uihk_seb_keys',
                array('ref_id' => array('integer', $ref_id),
                    'seb_key_win' => array('text', $seb_key_win),
                    'seb_key_macos' => array('text', $seb_key_macos))
            ) > 0) {
                return true;
            } else {
                return false;
            }
        }
    }
    
    private function readSEBConf() : void
    {
        $q = $this->db->query("SELECT * FROM ui_uihk_seb_conf");
        
        while ($row = $this->db->fetchAssoc($q)) {
            $this->conf[$row['name']] = $row['value'];
        }
        
        $seb_keys = explode(',', $this->conf['seb_keys']);
        
        $this->conf['seb_keys'] = array();
        
        foreach ($seb_keys as $seb_key) {
            $this->conf['seb_keys'][] = trim($seb_key);
        }
    }
    
    private function checkKeys(string $key_from_browser, array $keys_from_config, string $request_url) : bool
    {
        foreach ($keys_from_config as $key_from_config) {
            if ($key_from_browser === hash('sha256', $request_url . trim($key_from_config))) {
                return true;
            }
        }
        
        return false;
    }
}
