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

include_once 'class.ilSEBGlobalScreenModificationProvider.php';
include_once 'class.ilSEBConfig.php';
include_once 'class.ilSEBAccessChecker.php';

class ilSEBPlugin extends ilUserInterfaceHookPlugin
{
    const SEB_REQUEST_TYPES = [
        'not_a_seb_request' => 0,
        'seb_request_object_keys_unspecific' => 1,
        'seb_request' => 2,
        'seb_request_object_keys' => 3
    ];
    
    const REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS = [
        'context_check' => [
            ilContext::CONTEXT_WAC
        ],
        'path_check' => [
            'logout.php',
            '/src/GlobalScreen/Client/notify.php'
        ],
        'cmd_check' => [
            'getOSDNotifications',
            'removeOSDNotifications'
        ],
        'cmd_and_cmdclass_check' => [
            'ilpersonalprofilegui' => [
                'showPersonalData',
                'showPublicProfile',
                'savePersonalData',
                'savePublicProfile'
            ],
            'ilstartupgui' => [
                'getAcceptance',
                'confirmAcceptance'
            ]
        ]
    ];
    
    const REQ_HEADER = 'X-Safeexambrowser-Requesthash';
    const STANDARD_BASE_CLASS = 'ilUIPluginRouterGUI';
    const SEB_CHECK_KEY_GUI_DEFINITION = array(self::STANDARD_BASE_CLASS, ilSEBCheckKeyGUI::class);
    const CHECK_KEY_COMMAND = 'CheckKey';
    
    const SEB_DATA_MODE = [
        'header' => 0,
        'cookie' => 1,
        'none' => 2
    ];
    
    private static $forbidden = false;
    private static $kioskmode_checked = false;
    
    private $access_checker;
    
    private $seb_config;
    
    private $current_ref_id;
    
    public function __construct()
    {
        parent::__construct();
        global $DIC;
        
        /*
         * We don't want this to be executed on the commandline, as it makes the setup fail
         */
        if (php_sapi_name() === 'cli') {
            return;
        }
        
        /*
         * This is ugly, but we need this to avoid an endless loop when redirecting to the "Forbidden"-Page
         * See the Comment below for the one and only place this MUST be set.
         */
        if (self::$forbidden) {
            return;
        }
        
        $this->current_ref_id = $this->extractRefIdFromQuery($DIC->http()->request()->getQueryParams());
        $this->seb_config = new ilSEBConfig($DIC->database());
        
        $this->access_checker = new ilSEBAccessChecker(
            $this->getCurrentRefId(),
            $DIC->ctrl(),
            $DIC->user(),
            $DIC['ilAuthSession'],
            $DIC->rbac()->review(),
            $DIC->http(),
            $this->seb_config
        );
        
        $DIC->globalScreen()->layout()->meta()->addJs($this->getDirectory() . '/templates/default/seb.js', true);
        $DIC->ctrl()->setParameterByClass(ilUIPluginRouterGUI::class, 'ref_id', $this->current_ref_id);
        $DIC->globalScreen()->layout()->meta()->addOnloadCode("il.seb.saveAndCheckSEBKey('" .
            $DIC->ctrl()->getLinkTargetByClass(self::SEB_CHECK_KEY_GUI_DEFINITION, self::CHECK_KEY_COMMAND) . "');");
        
        if ($this->access_checker->isKeyCheckPossible() && !$this->access_checker->isCurrentUserAllowed()) {
            /*
             * This is ugly, but we need this to avoid an endless loop when redirecting to the "Forbidden"-Page
             * This is the one and only place this MUST be set.
             */
            self::$forbidden = true;
            
            $this->access_checker->exitIlias($this);
        }
        
        /*
         * We need to switch the kioskmode off in tests to avoid collitions in certain modification providers
         * for the GlobalScreen. We need to check this here, because there simply is no other place.
         */
        if (!self::$kioskmode_checked && 
            $this->access_checker->isSwitchToSebSkinNeeded() && 
            ilObject::_lookupType($this->current_ref_id, true) == 'tst'
        ) {
            $test = new ilObjTest($this->getCurrentRefId());
            if ($test->getKioskMode() === true) {
                $test->setKioskMode();
                $test->saveToDb();
            }
            
            self::$kioskmode_checked = true;
        }
        
        if ($this->access_checker->isSwitchToSebSkinNeeded() &&
            (self::$kioskmode_checked ||
            ilObject::_lookupType($this->current_ref_id, true) != 'tst')) {
            $this->provider_collection
           ->setModificationProvider(new ilSEBGlobalScreenModificationProvider($DIC, $this));
        }
    }
    
    public function getPluginName() : string
    {
        return "SEB";
    }
    
    public function getCurrentRefId() : ?int
    {
        return $this->current_ref_id;
    }
    
    public function isShowParticipantPicture() : bool
    {
        return $this->seb_config->getShowPaxPic();
    }
    
    public function handleEvent(string $a_component, string $a_event, array $a_parameter) : void
    {
        if ($a_event === 'afterLogin') {
            ilSession::clear('last_uri');
        }
    }
    
    private function extractRefIdFromQuery(array $query) : ?int
    {
        if (array_key_exists('ref_id', $query) && is_numeric($query['ref_id']) && $query['ref_id'] > 0) {
            return (int) $query['ref_id'];
        }
        
        if (array_key_exists('target', $query)) {
            return $this->extractRefIdFromTargetParameter($query['target']);
        }
        
        return null;
    }
    
    private function extractRefIdFromTargetParameter(String $target) : ?int
    {
        $target_array = explode('_', $target);
        if (is_numeric($target_array[1])  && $target_array[1] > 0) {
            return (int) $target_array[1];
        }
        
        return null;
    }
}
