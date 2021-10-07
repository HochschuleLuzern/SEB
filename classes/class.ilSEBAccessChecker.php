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

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\DI\HTTPServices;
use ILIAS\GlobalScreen\Client\Notifications;

class ilSEBAccessChecker
{
    private $ctrl;
    private $user;
    private $auth;
    private $rbacreview;
    private $http;
    private $conf;
    private $ref_id;
    
    private $current_user_allowed;
    public function isCurrentUserAllowed() : bool
    {
        return $this->current_user_allowed;
    }
    
    private $switch_to_seb_skin_needed;
    public function isSwitchToSebSkinNeeded() : bool
    {
        return $this->switch_to_seb_skin_needed;
    }
    
    /**
     * We set everying up in the constructor, populating all variables
     *
     * @param integer $ref_id of the repository object that the user wants to access
     */
    public function __construct(
        int $ref_id,
        ilCtrl $ctrl,
        ilObjUser $user,
        ilAuthSession $auth,
        ilRbacReview $rbacreview,
        HTTPServices $http,
        ilSEBConfig $conf
    )
    {
        $this->ctrl = $ctrl;
        $this->user = $user;
        $this->auth = $auth;
        $this->rbacreview = $rbacreview;
        $this->http = $http;
        $this->conf = $conf;
        
        $this->ref_id = $this->retrieveRefId();
        
        $is_logged_in = ($this->user->id && $this->user->id != ANONYMOUS_USER_ID);
        $is_root = $this->rbacreview->isAssigned($this->user->id, SYSTEM_ROLE_ID);
        $this->setCurrentUserAllowed($is_logged_in, $is_root);
        $this->setSwitchToSEBSkinNeeded($is_logged_in, $is_root);
    }
    
    public function exitIlias(ilSEBPlugin $pl)
    {
        $response = $this->http->response();
        $request = $this->http->request();
        
        ilSession::setClosingContext(ilSession::SESSION_CLOSE_LOGIN);
        if ($this->auth->isValid()) {
            $this->auth->logout();
        }
        session_unset();
        session_destroy();
        
        $target_str = '';
        if ($this->ref_id) {
            $target_str = 'target=' . \ilObject::_lookupType($this->ref_id, true) . '_' . $this->ref_id . '&';
        } elseif ($target = $request->getQueryParams()['target']) {
            $target_str = 'target=' . $target . '&';
        }
        
        $response = $response->withStatus(403, "Forbidden");
            
        $tpl = $pl->getTemplate('default/tpl.seb_forbidden.html');
        $tpl->setCurrentBlock('seb_forbidden_message');
        $tpl->setVariable('SEB_FORBIDDEN_HEADER', $pl->txt('forbidden_header'));
        $tpl->setVariable('SEB_FORBIDDEN_MESSAGE', $pl->txt('forbidden_message'));
        $tpl->setVariable('SEB_LOGIN_LINK', "login.php?" . $target_str . "client_id=" . rawurlencode(CLIENT_ID) . "&cmd=force_login&lang=" . $this->user->getCurrentLanguage());
        $tpl->setVariable('SEB_LOGIN_LINK_TEXT', $pl->txt('forbidden_login'));
        $tpl->parseCurrentBlock();
        $response = $response->withBody(Streams::ofString($tpl->get()));
        $this->http->saveResponse($response);
        $this->http->sendResponse();
        exit;
    }
    
    private function setCurrentUserAllowed(bool $is_logged_in, bool $is_root)
    {
        $this->current_user_allowed = false;
        $role_deny = $this->conf->getRoleDeny();
        $allow_without_seb = true;
        
        if ($is_logged_in && $role_deny && !$is_root) {
            $allow_without_seb = !($role_deny === 1 || $this->rbacreview->isAssigned($this->user->id, $role_deny));
        }
        
        if ($allow_without_seb ||
            ($this->detectSeb($this->ref_id) > 1) ||
            ($this->anySEBKeyIsEnough() && $this->detectSeb(0))) {
            $this->current_user_allowed = true;
        }
    }
    
    private function anySEBKeyIsEnough() : bool
    {
        $cmd = $this->ctrl->getCmd();
        $cmdclass = $this->ctrl->getCmdClass();
        $path = $this->http->request()->getUri()->getPath();
        
        if (in_array(ilContext::getType(), ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['context_check']) ||
            in_array($cmd, ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_check']) ||
            (array_key_exists($cmdclass, ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_and_cmdclass_check']) &&
                in_array($cmd, ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_and_cmdclass_check'][$cmdclass]))
            ) {
            return true;
        }
        
        foreach (ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['path_check'] as $exempted_path) {
            if (mb_strpos($path, $exempted_path)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function setSwitchToSEBSkinNeeded(bool $is_logged_in, bool $is_root)
    {
        $is_kiosk_user = (($this->conf->getRoleKiosk() == 1 || $this->rbacreview->isAssigned($this->user->id, $this->conf->getRoleKiosk())) && !$is_root);
        
        if ($is_logged_in && $is_kiosk_user) {
            $this->switch_to_seb_skin_needed = true;
        } else {
            $this->switch_to_seb_skin_needed = false;
        }
    }
    
    private function detectSeb(?int $ref_id) : int
    {
        $server_req_header = $this->http->request()->getHeader(ilSEBPlugin::REQ_HEADER)[0];
        
        if (!$server_req_header || $server_req_header == "") {
            return ilSebPlugin::NOT_A_SEB_REQUEST;
        }
        if ($this->conf->checkSebKey($server_req_header, $this->buildFullUrl())) {
            return ilSEBPlugin::SEB_REQUEST;
        }
        if ($this->conf->checkObjectKey($server_req_header, $this->buildFullUrl(), $ref_id)) {
            return ilSEBPlugin::SEB_REQUEST_OBJECT_KEYS;
        }
        if (!$ref_id && $this->conf->checkKeyAgainstAllObjectKeys($server_req_header, $this->buildFullUrl())) {
            return ilSEBPlugin::SEB_REQUEST_OBJECT_KEYS_UNSPECIFIC;
        }
        
        return ilSebPlugin::NOT_A_SEB_REQUEST;
    }
    
    private function retrieveRefId() : ?int
    {
        $ref_id = $this->http->request()->getQueryParams()['ref_id'];
        
        if (is_null($ref_id) || !is_numeric($ref_id)) {
            $ref_id = $this->retrieveRefIdFromTarget();
        }
        
        if (is_null($ref_id) || $ref_id < 1) {
            return null;
        }
        
        return (int) $ref_id;
    }
    
    private function retrieveRefIdFromTarget() : ?int
    {
        $target_string = $this->http->request()->getQueryParams()['target'];
        if (is_null($target_string)) {
            return null;
        }
        
        $target_array = explode('_', $target_string);
        if (!is_numeric($target_array[1])) {
            return null;
        }
        
        return (int) $target_array[1];
    }
    
    private function buildFullUrl() : string
    {
        $uri = $this->http->request()->getUri();
        $protocol = $uri->getScheme();
        $port = $uri->getPort();
        $host = $uri->getHost();
        $path = $uri->getPath();
        $query = $uri->getQuery();
        
        if ($query != '') {
            $query = '?' . $query;
        }
        
        
        return $protocol . "://" . $host . $port . $path . $query;
    }
}
