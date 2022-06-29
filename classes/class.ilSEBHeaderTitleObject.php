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

class ilSEBHeaderTitleObject
{
    private $plugin;
    private $user;
    private $object;
    
    public function __construct(ilSEBPlugin $plugin, ilObjUser $user)
    {
        $this->plugin = $plugin;
        $this->user = $user;
    }
    
    public function withObject(ilObject $object) : ilSEBHeaderTitleObject
    {
        $clone = clone $this;
        $clone->object = $object;
        return $clone;
    }
    
    public function getParsedTitleString() : string
    {
        global $ilSetting;
        
        $template = new ilTemplate('tpl.il_as_tst_kiosk_head.html', true, true, $this->plugin->getDirectory());
        
        if ($this->user->getId() > 0) {
            $template->setVariable('PARTICIPANT_NAME', $this->user->getFullname());
            
            $matriculation = null;
            $username = null;
            
            if ($this->plugin->isShowParticipantMatriculation() && $this->user->getMatriculation() !== '') {
                $matriculation = $this->user->getMatriculation();
            }
            if ($this->plugin->isShowParticipantUsername()) {
                $username = $this->user->getLogin();
            }
            
            $additional_info = '';
            
            if (!is_null($username) && !is_null($matriculation)) {
                $additional_info = '(' . $username . ' - ' . $matriculation . ')';
            } elseif (!is_null($username) || !is_null($matriculation)) {
                $to_be_shown = $username ?? $matriculation;
                $additional_info = '(' . $to_be_shown . ')';
            }
            
            $template->setVariable('ADDITIONAL_INFO', $additional_info);
        }
        
        if (is_null($this->object)) {
            return $template->get();
        }
        
        if ($this->object->getType() === 'tst') {
            $template->setVariable("TITLE", $this->object->getTitle());
        }
        else {
            $template->setVariable("TITLE", $ilSetting->get('short_inst_name'));
        }

        if ($this->object->getType() === 'tst' && $this->object->isShowExamIdInTestPassEnabled()) {
            $template->setVariable("EXAM_ID_TXT", '(' . $this->plugin->txt("exam_id") . ": ");
            $testSession = new ilTestSession();
            $testSession->loadTestSession($this->object->getTestId(), $this->user->getId());
            $exam_id = ilObjTest::buildExamId(
                $testSession->getActiveId(),
                $testSession->getPass(),
                $this->plugin->getCurrentRefId()
            );
            $template->setVariable('EXAM_ID', $exam_id . ')');
        }
        
        return $template->get();
    }
}
