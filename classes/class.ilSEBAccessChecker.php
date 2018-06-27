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

include_once("class.ilSEBPlugin.php");
include_once("class.ilSEBConfig.php");

class ilSEBAccessChecker {
    private $conf;
    private $auth;
    
    /*
     * Getter Functions
     */
    
    /**
     * Is the browser accessing ILIAS a Safe Exam Browser?
     * 
     * @var integer
     */
    private $is_seb;
    
    /**
     * Is the browser accessing ILIAS a Safe Exam Browser?
     * 
     * @return integer See constants in ilSEBPlugin
     */
    public function isSeb() {
        return $this->is_seb;
    }
    
    /**
     * Is the browser accessing ILIAS a Safe Exam Browser?
     *
     * @var boolean
     */
    private $needs_seb;
    
    /**
     * Is the browser accessing ILIAS a Safe Exam Browser?
     *
     * @return boolean
     */
    public function getNeedsSeb() {
        return $this->needs_seb;
    }
    
    /**
     * To we need to switch to kiosk/seb template
     *
     * @var boolean
     */
    private $switch_to_seb_skin;
    
    /**
     * To we need to switch to kiosk/seb template
     *
     * @return boolean
     */
    public function getSwitchToSebSkin() {
        return $this->switch_to_seb_skin;
    }
    
    /**
     * We set everying up in the constructor, populating all variables
     * 
     * @param integer $ref_id of the repository object that the user wants to access
     */
    public function __construct($ref_id) {
        global $DIC;
        $this->auth = $DIC['ilAuthSession'];
        $user = $DIC->user();
        $rbacreview = $DIC->rbac()->review();
        $this->conf = ilSEBConfig::getInstance();
        
        $this->is_seb = $this->detectSeb($ref_id);
        
        $is_logged_in = ($user->id && $user->id != ANONYMOUS_USER_ID);
        
        $is_root = $rbacreview->isAssigned($user->id,SYSTEM_ROLE_ID);
        
        $role_deny = (int) $this->conf->getRoleDeny();
        
        // check role deny
        if ($is_logged_in && $role_deny && !$is_root) {
            // check access
            $this->needs_seb = ($role_deny == 1 || $rbacreview->isAssigned($user->id,$role_deny));
        } else {
            $this->needs_seb = false;
        }
                
        // check if a switch to the seb skin is needed
        $is_kiosk_user = (($this->conf->getRoleKiosk() == 1 || $rbacreview->isAssigned($user->id,$this->conf->getRoleKiosk())) && !$is_root);
        
        if ($is_logged_in && $is_kiosk_user) {
            $this->switch_to_seb_skin = true;
        } else {
            $this->switch_to_seb_skin = false;
        }
    }
    
    /**
     * Delete all session information and redirect to the Access forbidden page.
     * 
     */
    public function exitIlias() {
        $pl = ilSEBPlugin::getInstance();
        ilSession::setClosingContext(ilSession::SESSION_CLOSE_LOGIN);
        
        if (is_object($this->auth->isValid())) {
            $this->auth->logout();
        }
        
        session_unset();
        session_destroy();

        $tpl = $pl->getTemplate('default/tpl.seb_forbidden.html');        
        $tpl->setCurrentBlock('seb_forbidden_message');
        $tpl->setVariable('SEB_FORBIDDEN_HEADER', $pl->txt('forbidden_header'));
        $tpl->setVariable('SEB_FORBIDDEN_MESSAGE', $pl->txt('forbidden_message'));
        $tpl->setVariable('SEB_LOGIN_LINK', $pl->txt('forbidden_login'));
        $tpl->parseCurrentBlock();
        header('HTTP/1.1 403 Forbidden');
        echo $tpl->get();
        exit;
    }
    
    /**
     * Helper function. If $ref_id = 0 we check if the current seb-header contains an object key of
     * another object.
     *
     * @param integer $ref_id of the repository object that the user wants to access
     * 
     * @return integer The type of request we are dealing with (see ilPlugin contants)
     */
    public function detectSeb($ref_id) {
        $server_req_header = $_SERVER[ilSEBPlugin::REQ_HEADER];
        
        /*
         * We detect if we are dealing with a SEB-Browser bei analyzing a request header.
         */
        if (!$server_req_header || $server_req_header == "") {
            return ilSebPlugin::NOT_A_SEB_REQUEST;
        } else if ($this->conf->checkSebKey($server_req_header, $this->getFullUrl())) {
            return ilSEBPlugin::SEB_REQUEST;
        } else if (!$ref_id && $this->conf->checkKeyAgainstAllObjectKeys($server_req_header, $this->getFullUrl())) {
            return ilSEBPlugin::SEB_REQUEST_OBJECT_KEYS_UNSPECIFIC;
        } else if ($this->conf->checkObjectKey($server_req_header, $this->getFullUrl(), $ref_id)) {
            return ilSEBPlugin::SEB_REQUEST_OBJECT_KEYS;
        } else {
            return ilSebPlugin::NOT_A_SEB_REQUEST;
        }
    }
    
    /**
     * We need the full url the request was sent to as it is part of the hash value in the seb-header
     * 
     * @return string Full Url of the request
     */
    private function getFullUrl() {
        $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
        $sp = strtolower($_SERVER["SERVER_PROTOCOL"]);
        $protocol = substr($sp, 0, strpos($sp, "/")) . $s;
        $port = ($_SERVER["SERVER_PORT"] == "80" || $_SERVER["SERVER_PORT"] == "443") ? "" : (":".$_SERVER["SERVER_PORT"]);
        
        return $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
    }
}