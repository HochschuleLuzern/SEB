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

include_once 'class.ilSEBHeaderTitleObject.php';

use ILIAS\GlobalScreen\Scope\Layout\Factory\MainBarModification;
use ILIAS\GlobalScreen\Scope\Layout\Provider\AbstractModificationPluginProvider;
use ILIAS\GlobalScreen\ScreenContext\Stack\CalledContexts;
use ILIAS\GlobalScreen\ScreenContext\Stack\ContextCollection;
use ILIAS\UI\Component\MainControls\MainBar;
use ILIAS\UI\Component\MainControls\MetaBar;
use ILIAS\GlobalScreen\Scope\Layout\Factory\BreadCrumbsModification;
use ILIAS\UI\Component\Breadcrumbs\Breadcrumbs;
use ILIAS\UI\Component\MainControls\Footer;
use ILIAS\GlobalScreen\Scope\Layout\Factory\FooterModification;
use ILIAS\UI\Component\Image\Image;
use ILIAS\GlobalScreen\Scope\Layout\Factory\MetaBarModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\LogoModification;
use ILIAS\GlobalScreen\Scope\Layout\Factory\TitleModification;

class ilSEBGlobalScreenModificationProvider extends AbstractModificationPluginProvider
{
    public function isInterestedInContexts() : ContextCollection
    {
        return $this->context_collection->main();
    }

    public function getMainBarModification(CalledContexts $screen_context_stack) : ?MainBarModification
    {
        return $this->dic->globalScreen()->layout()->factory()->mainbar()->withModification(
            function (MainBar $current = null) : ?MainBar {
                $empty_mainbar = $this->dic->ui()->factory()->mainControls()->mainBar();
                $this->addCSS();
                return $empty_mainbar;
            }
        )->withHighPriority();
    }
    
    public function getMetaBarModification(CalledContexts $screen_context_stack) : ?MetaBarModification
    {
        return $this->dic->globalScreen()->layout()->factory()->metabar()->withModification(
            function (MetaBar $current = null) : MetaBar {
                $empty_metabar = $current->withClearedEntries();
                if (!$this->isTestRunning()) {
                    $empty_metabar = $this->withLanguageAndLogout($empty_metabar);
                }
                return $empty_metabar;
            }
        )->withHighPriority();
    }
    
    public function getLogoModification(CalledContexts $screen_context_stack) : ?LogoModification
    {
        return $this->dic->globalScreen()->layout()->factory()->logo()->withModification(
            function (Image $current = null) : ?Image {
                $logo_path = './Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SEB/templates/images/HeaderIcon.png';
                $logo_alt = 'SEB Logo';
                if ($this->plugin->isShowParticipantPicture()) {
                    $logo_path = $this->dic->user()->getPersonalPicturePath('small', true);
                    $logo_alt = $this->dic->user()->getFullname();
                }
                $image = $this->dic->ui()->factory()->image()->standard($logo_path, $logo_alt);
                    

                $image = $this->withLogoAction($image);
                return $image;
            }
        )->withHighPriority();
    }
    
    public function getTitleModification(CalledContexts $screen_context_stack) : ?TitleModification
    {
        return $this->dic->globalScreen()->layout()->factory()->title()->withModification(
            function (String $current = null) : string {
                $title_object = $this->initializeHeaderTitleObject();
                return $title_object->getParsedTitleString();
            }
        )->withHighPriority();
    }
    
    public function getBreadCrumbsModification(CalledContexts $screen_context_stack) : ?BreadCrumbsModification
    {
        return $this->dic->globalScreen()->layout()->factory()->breadcrumbs()->withModification(
            function (Breadcrumbs $current = null) : ?Breadcrumbs {
                return null;
            }
        )->withHighPriority();
    }
    
    public function getFooterModification(CalledContexts $screen_context_stack) : ?FooterModification
    {
        return $this->dic->globalScreen()->layout()->factory()->footer()->withModification(
            function (Footer $current = null) : ?Footer {
                return $this->dic->ui()->factory()->mainControls()->footer([], '');
            }
        )->withHighPriority();
    }
    
    private function initializeHeaderTitleObject() : ilSEBHeaderTitleObject
    {
        $title_object = new ilSEBHeaderTitleObject($this->plugin, $this->dic->user());
        if ($this->plugin->getCurrentRefId() !== Null && $this->plugin->getCurrentRefId() !== 0) {
            $object = ilObjectFactory::getInstanceByRefId($this->plugin->getCurrentRefId());
            $title_object = $title_object->withObject($object);
        }
                
        return $title_object;
    }
    
    private function isTestRunning() : bool
    {
        if ($this->dic->ctrl()->getContextObjType() != 'tst') {
            return false;
        }
        
        $object = new ilObjTest($this->plugin->getCurrentRefId());
        $testSession = new ilTestSession();
        $testSession->loadTestSession($object->getTestId(), $this->dic->user()->getId());

        if ($testSession->getActiveId() == 0 ||
            $testSession->getLastStartedPass() === $testSession->getLastFinishedPass()) {
            return false;
        }
        
        return true;
    }
    
    private function withLanguageAndLogout(MetaBar $meta_bar) : MetaBar
    {
        $f = $this->dic->ui()->factory();
        
        $user = $this->dic->user();
        if ($user->getCurrentLanguage() != null && $user->getLanguage() != $user->getCurrentLanguage()) {
            $user->setLanguage($user->getCurrentLanguage());
            $user->update();
        }
        
        $languages = $this->getEntriesForAvailableLanguages();
        
        if (count($languages) > 1) {
            $lang_entry = $f->mainControls()->slate()->combined('language', $f->symbol()->glyph()
                ->language());
            foreach ($languages as $language) {
                $lang_entry = $lang_entry->withAdditionalEntry($language);
            }
            
            $meta_bar = $meta_bar->withAdditionalEntry('lang_menu', $lang_entry);
        }
        
        $logout_label = $this->dic->language()->txt('logout');
        $logout_entry = $f->button()->bulky($f->symbol()->glyph()
            ->logout(), $logout_label, "logout.php?lang=" . $this->dic->user()->getCurrentLanguage());
        return $meta_bar->withAdditionalEntry('logout', $logout_entry);
    }
    
    private function getEntriesForAvailableLanguages()
    {
        $f = $this->dic->ui()->factory();
        $languages = $this->dic->language()->getInstalledLanguages();
        $language_selection = [];
        
        $base = $this->getBaseURL();
        
        foreach ($languages as $lang_key) {
            $link = $this->appendUrlParameterString($base, "lang=" . $lang_key);
            $language_name = $this->dic->language()->_lookupEntry($lang_key, "meta", "meta_l_" . $lang_key);
            
            $language_icon = $f->symbol()->icon()->standard("none", $language_name)
            ->withAbbreviation($lang_key);
            
            $language_selection[] = $f->button()->bulky($language_icon, $language_name, $link);
        }
        
        return $language_selection;
    }
    
    private function withLogoAction(Image $image) : Image
    {
        $url = ilUserUtil::getStartingPointAsUrl();
        if (!$url) {
            $url = "./goto.php?target=root_1";
        }
        
        if ($this->isTestRunning()) {
            $url = "#";
        }
        
        return $image->withAction($url);
    }
    
    private function appendUrlParameterString(string $existing_url, string $addition) : string
    {
        $url = (is_int(strpos($existing_url, "?")))
        ? $existing_url . "&" . $addition
        : $existing_url . "?" . $addition;
        
        $url = str_replace("?&", "?", $url);
        
        return $url;
    }
    
    private function getBaseURL() : string
    {
        $base = substr($_SERVER["REQUEST_URI"], strrpos($_SERVER["REQUEST_URI"], "/") + 1);
        
        return preg_replace("/&*lang=[a-z]{2}&*/", "", $base);
    }
    
    private function addCSS()
    {
        $this->dic->ui()->mainTemplate()->addCss($this->plugin->getStyleSheetLocation('default/seb.css'));
        
        if ($this->plugin->isShowParticipantPicture()) {
            $this->dic->ui()->mainTemplate()->addCss($this->plugin->getStyleSheetLocation('default/seb_with_profile_picture.css'));
        }
        
        if ($this->isTestRunning()) {
            $this->dic->ui()->mainTemplate()->addCss($this->plugin->getStyleSheetLocation('default/seb_test_running.css'));
        }
    }
}
