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

include 'class.ilSEBGlobalScreenModificationProvider.php';
include 'class.ilSEBConfig.php';

class ilSEBPlugin extends ilUserInterfaceHookPlugin
{
	const NOT_A_SEB_REQUEST = 0;
	const SEB_REQUEST_OBJECT_KEYS_UNSPECIFIC = 1;
	const SEB_REQUEST = 2;
	const SEB_REQUEST_OBJECT_KEYS = 3;
	const ROLES_NONE = 0;
	const ROLES_ALL = 1;
	const BROWSER_KIOSK_ALL = 0;
	const BROWSER_KIOSK_SEB = 1;
	const CACHE = 'SEB_CONFIG_CACHE';
	const REQ_HEADER = 'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH';
	
	private static $forbidden = false;
	private static $kioskmode_checked = false;
	
	private $access_checker;
	
	private $seb_config;
	
	private $current_ref_id;
	
	public function __construct() {
	    parent::__construct();
	    
	    /*
	     * This is ugly, but we need this to avoid an endless loop when redirecting to the "Forbidden"-Page
	     * See the Comment below for the one and only place this MUST be set.
	     */
	    if (self::$forbidden) {
	        return;
	    }
	    
	    global $DIC;
	    
	    $this->current_ref_id = (int) $DIC->http()->request()->getQueryParams()['ref_id'];
	    
	    $this->seb_config = new ilSEBConfig();
	    
	    $this->access_checker = new ilSEBAccessChecker($this->getCurrentRefId(), 
	        $DIC->user(), 
	        $DIC['ilAuthSession'], 
	        $DIC->rbac()->review(),
	        $this->seb_config);
	    
	    if ($this->access_checker->denyWithoutSeb()) {
	        /*
	         * This is ugly, but we need this to avoid an endless loop when redirecting to the "Forbidden"-Page
	         * This is the one and only place this MUST be set.
	         */
	        self::$forbidden = true;
	        
	        $this->access_checker->exitIlias(
	               $this
	            );
	    }
	    
	    if (!self::$kioskmode_checked && $DIC->ctrl()->getContextObjType() == 'tst') {
	        $test = new ilObjTest($this->getCurrentRefId());
	        if ($test->getKioskMode() === true) {
	            $test->setKioskMode();
	            $test->saveToDb();
	        }
	        
	        self::$kioskmode_checked = true;
	    }
	    
	    if ($this->access_checker->isSwitchToSebSkinNeeded() &&
	        (self::$kioskmode_checked ||
	        $DIC->ctrl()->getContextObjType() != 'tst')) {
    	    $this->provider_collection
	       ->setModificationProvider(new ilSEBGlobalScreenModificationProvider($DIC, $this));
	    }
	}
	
	public function getPluginName() : string {
		return "SEB";
	}
	
	public function getCurrentRefId() : int {
	    return $this->current_ref_id;
	}
	
	public function isShowParticipantPicture() : bool {
	    return $this->seb_config->getShowPaxPic();
	}
}

?>
