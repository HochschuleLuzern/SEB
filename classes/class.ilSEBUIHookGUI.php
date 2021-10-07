<?php declare(strict_types=1);
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

class ilSEBUIHookGUI extends ilUIHookPluginGUI
{
    const STANDARD_BASE_CLASS = 'ilUIPluginRouterGUI';
        
    public function modifyGUI($a_comp, $a_part, $a_par = array())
    {
        if ($a_part == 'tabs') {
            global $DIC;
            $conf = new ilSEBConfig($DIC->database());
            
            $ref_id = (int) $DIC->http()->request()->getQueryParams()['ref_id'];
            $obj_id = ilObject::_lookupObjectId($ref_id);
            $obj_type = ilObject::_lookupType($obj_id);
            $has_write_access = $DIC->rbac()->system()->checkAccessOfUser($DIC->user()->getId(), 'write', $ref_id);
    
            if ($obj_type == 'tst' && $has_write_access && $_GET['cmd'] != 'showQuestion' && $_GET['cmd'] != 'outUserResultsOverview') {
                /*
    	         * Add Sessioncontrol Tab for SEB
    	         **/
                if ($conf->getActivateSessionControl() &&
                    !in_array($_GET['cmdClass'], ['ilsebsettingstabgui', 'iltestevaluationgui', 'ilobjectactivationgui']) &&
                    !($_GET['cmdClass'] == 'iltestcorrectionsgui' && $_GET['cmd'] != 'showQuestionList')) {
                    $security = ilSecuritySettings::_getInstance();
                    if ($security->isPreventionOfSimultaneousLoginsEnabled()) {
                        $DIC->ctrl()->setParameterByClass('ilSEBSessionsTabGUI', 'ref_id', $ref_id);
                        $link = $DIC->ctrl()->getLinkTargetByClass(array(
                                self::STANDARD_BASE_CLASS,
                                'ilSEBSessionsTabGUI'
                        ), 'showSessions');
                        $a_par['tabs']->addTab('sessions', $this->getPluginObject()->txt('sessions_tab_title'), $link);
                    }
                }
                
                /*
    	         * Add Settings Tab for SEB
    	         **/
                if ($conf->getAllowObjectKeys() &&
                        !in_array($_GET['cmdClass'], ['ilsebsessionstabgui', 'iltestevaluationgui', 'ilobjectactivationgui']) &&
                        !($_GET['cmdClass'] == 'iltestcorrectionsgui' && $_GET['cmd'] != 'showQuestionList')) {
                    $DIC->ctrl()->setParameterByClass('ilSEBSettingsTabGUI', 'ref_id', $ref_id);
                    $link = $DIC->ctrl()->getLinkTargetByClass(array(
                        self::STANDARD_BASE_CLASS,
                        'ilSEBSettingsTabGUI'
                    ), 'seb_settings');
                    $a_par['tabs']->addTab("seb_settings", $this->getPluginObject()->txt('settings_tab_title'), $link);
                }
            }
        }
    }
}
