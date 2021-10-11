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

include 'class.ilSEBGlobalScreenModificationProvider.php';
include 'class.ilSEBConfig.php';
include 'class.ilSEBAccessChecker.php';

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
    const REQ_HEADER = 'X-Safeexambrowser-Requesthash';
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
    
    private static $forbidden = false;
    private static $kioskmode_checked = false;
    
    private $access_checker;
    
    private $seb_config;
    
    private $current_ref_id;
    
    public function __construct()
    {
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
        $this->seb_config = new ilSEBConfig($DIC->database());
        
        /*
         * We need to switch the kioskmode off in tests to avoid collitions in certain modification providers
         * for the GlobalScreen. We need to check this here, because there simply is no other place.
         */
        if (!self::$kioskmode_checked && ilObject::_lookupType($this->current_ref_id, true) == 'tst') {
            $test = new ilObjTest($this->getCurrentRefId());
            if ($test->getKioskMode() === true) {
                $test->setKioskMode();
                $test->saveToDb();
            }
            
            self::$kioskmode_checked = true;
        }
        
        $this->access_checker = new ilSEBAccessChecker(
            $this->getCurrentRefId(),
            $DIC->ctrl(),
            $DIC->user(),
            $DIC['ilAuthSession'],
            $DIC->rbac()->review(),
            $DIC->http(),
            $this->seb_config
        );
        if (!$this->access_checker->isCurrentUserAllowed()) {
            /*
             * This is ugly, but we need this to avoid an endless loop when redirecting to the "Forbidden"-Page
             * This is the one and only place this MUST be set.
             */
            self::$forbidden = true;
            
            $this->access_checker->exitIlias($this);
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
    
    public function getCurrentRefId() : int
    {
        return $this->current_ref_id;
    }
    
    public function isShowParticipantPicture() : bool
    {
        return $this->seb_config->getShowPaxPic();
    }
}
