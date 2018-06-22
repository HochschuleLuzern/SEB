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

include_once("./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");

class ilSEBPlugin extends ilUserInterfaceHookPlugin
{
	const NOT_A_SEB_REQUEST = 0;
	const SEB_REQUEST = 1;
	const SEB_REQUEST_OBJECT_KEYS = 2;
	const SEB_REQUEST_OBJECT_KEYS_UNSPECIFIC = 3;
	const ROLES_NONE = 0;
	const ROLES_ALL = 1;
	const BROWSER_KIOSK_ALL = 0;
	const BROWSER_KIOSK_SEB = 1;
	const CACHE = 'SEB_CONFIG_CACHE';
	const REQ_HEADER = 'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH';
	
	private static $instance;
	
	public static function getInstance() {
	    if (isset(self::$instance)) {
	        return self::$instance;
	    } else {
	        self::$instance = new self;
	        return self::$instance;
	    }
	}
	
	private static function _isAPCInstalled() {
		return (function_exists("apc_store") && function_exists("apc_fetch"));
	}
	
	public static function _flushAPC() {
		if (ilSEBPlugin::_isAPCInstalled() && apc_exists(ilSEBPlugin::CACHE))  {
			apc_delete(ilSEBPlugin::CACHE);
		}
	}
	
	function getPluginName() {
		return "SEB";
	}
}

?>
