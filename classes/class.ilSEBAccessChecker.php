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

class ilSEBAccessChecker
{
    private $ctrl;
    private $user;
    private $auth;
    private $rbacreview;
    private $http;
    private $conf;
    private $data;
    private $ref_id;
    private $mode;

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
        ?int $ref_id,
        ilCtrl $ctrl,
        ilObjUser $user,
        ilAuthSession $auth,
        ilRbacReview $rbacreview,
        HTTPServices $http,
        ilSEBConfig $conf
    ) {
        $this->ctrl = $ctrl;
        $this->user = $user;
        $this->auth = $auth;
        $this->rbacreview = $rbacreview;
        $this->http = $http;
        $this->conf = $conf;

        $this->mode = $this->sebKeyInHeaderPostCookieOrNone();
        $this->data = $this->retrieveSEBData($this->mode);
        $this->ref_id = $ref_id;

        $is_logged_in = ($this->user->id && $this->user->id != ANONYMOUS_USER_ID);
        $is_root = $this->rbacreview->isAssigned($this->user->id, SYSTEM_ROLE_ID);
        $this->switch_to_seb_skin_needed = $this->detectSwitchToSEBSkinNeeded($is_logged_in, $is_root);
        $this->current_user_allowed = $this->detectCurrentUserAllowed($is_logged_in, $is_root);

        ilSession::set('last_uri', $this->retrieveFullUri());
    }

    public function isKeyCheckPossibleOrUnavoidable() : bool
    {
        if ($this->mode === ilSEBPlugin::SEB_DATA_MODE['none'] && $this->data['uri'] === '' && $this->data['last_uri'] !== '') {
            return false;
        }

        return true;
    }

    public function exitIlias(ilSEBPlugin $pl) : void
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

    private function sebKeyInHeaderPostCookieOrNone() : int
    {
        if ($this->http->request()->hasHeader(ilSEBPlugin::REQ_HEADER)) {
            return ilSEBPlugin::SEB_DATA_MODE['header'];
        }
        if (isset($_COOKIE['examKey']) && isset($_COOKIE['uri'])) {
            return ilSEBPlugin::SEB_DATA_MODE['cookie'];
        }
        return ilSEBPlugin::SEB_DATA_MODE['none'];
    }

    private function detectCurrentUserAllowed(bool $is_logged_in, bool $is_root) : bool
    {
        $role_deny = $this->conf->getRoleDeny();
        $allow_without_seb = true;

        if ($is_logged_in && $role_deny && !$is_root) {
            $allow_without_seb = !($role_deny === 1 || $this->rbacreview->isAssigned($this->user->id, $role_deny));
        }

        if ($allow_without_seb ||
            ($this->detectSeb($this->ref_id) >= ilSEBPlugin::SEB_REQUEST_TYPES['seb_request']) ||
            ($this->anySEBKeyIsEnough() && $this->detectSeb())) {
            return true;
        }

        return false;
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

    private function detectSwitchToSEBSkinNeeded(bool $is_logged_in, bool $is_root) : bool
    {
        $is_kiosk_user = (($this->conf->getRoleKiosk() == 1 || $this->rbacreview->isAssigned($this->user->id, $this->conf->getRoleKiosk())) && !$is_root);

        if ($is_logged_in && $is_kiosk_user) {
            return true;
        }

        return false;
    }

    private function detectSeb(?int $ref_id = null) : int
    {
        $exam_key = $this->data['exam_key'];

        if ($exam_key === "") {
            return ilSebPlugin::SEB_REQUEST_TYPES['not_a_seb_request'];
        }

        if ($this->conf->checkSebKey($exam_key, $this->data['uri'])) {
            return ilSebPlugin::SEB_REQUEST_TYPES['seb_request'];
        }
        if ($this->conf->checkObjectKey($exam_key, $this->data['uri'], $ref_id)) {
            return ilSebPlugin::SEB_REQUEST_TYPES['seb_request_object_keys'];
        }

        if (!$ref_id && $this->conf->checkKeyAgainstAllObjectKeys($exam_key, $this->data['uri'])) {
            return ilSebPlugin::SEB_REQUEST_TYPES['seb_request_object_keys_unspecific'];
        }

        return ilSebPlugin::SEB_REQUEST_TYPES['seb_request_invalid'];
    }

    private function retrieveSEBData(int $mode) : array
    {
        $data = [];

        switch ($mode) {
            case ilSEBPlugin::SEB_DATA_MODE['header']:
                $data['exam_key'] = $this->http->request()->getHeader(ilSEBPlugin::REQ_HEADER)[0];
                $data['uri'] = $this->retrieveFullUri();
                break;
            case ilSEBPlugin::SEB_DATA_MODE['cookie']:
                $data['exam_key'] = $_COOKIE['examKey'];
                $data['uri'] = $_COOKIE['uri'];
                break;
            default:
                $data['exam_key'] = '';
                $data['uri'] = isset($_COOKIE['uri']) ? $_COOKIE['uri'] : '';
                break;
        }

        $data['last_uri'] = ilSession::get('last_uri');
        return $data;
    }

    private function retrieveFullUri() : string
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

        if ($this->conf->getIliasRootUri() !== '') {
            $root_uri = new \ILIAS\Data\URI($this->conf->getIliasRootUri());
            $protocol = $root_uri->getHost();
            $port = $root_uri->getPort();
            $host = $root_uri->getHost();
        }

        return $protocol . "://" . $host . $port . $path . $query;
    }
}
