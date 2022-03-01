<?php declare(strict_types = 1);
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

/**
 * Class ilSEBCheckKeyGUI
 * @author Stephan Winiker <stephan.winiker@hslu.ch>
 * @ilCtrl_isCalledBy    ilSEBCheckKeyGUI: ilUIPluginRouterGUI
 */
class ilSEBCheckKeyGUI
{
    public function executeCommand() {
        global $DIC;
        $cmd = $DIC->ctrl()->getCmd();
        switch ($cmd) {
            case ilSEBPlugin::CHECK_KEY_COMMAND:
                $this->$cmd();
                break;
        }
    }
    
    private function checkKey() {
        echo true;
    }
}