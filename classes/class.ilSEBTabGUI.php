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

/**
 * All needed includes
 *
 * The includes from ILIAS core can be removed with ILIAS5.3
 */
include_once 'Services/Repository/classes/class.ilRepositoryExplorer.php';

/**
 * Abstract GUI class implementing the shared functionality for the tabs in the SEB plugin
 * 
 * @author Stephan Winiker <stephan.winiker@hslu.ch>
 */
abstract class ilSEBTabGUI {
	protected $tpl;
	protected $obj_def;
	protected $ctrl;
	protected $tabs;
	protected $lang;
	protected $ref_id;
	protected $object;
	
	function __construct() {
		global $DIC;
		
		$this->tpl = $DIC->ui()->mainTemplate();
		$this->ctrl = $DIC->ctrl();
		$this->tabs = $DIC->tabs();
		$this->obj_def = $DIC['objDefinition'];
		$this->lang = $DIC->language();
		$this->ref_id = $_GET['ref_id'];
		$this->object = ilObjectFactory::getInstanceByRefId($this->ref_id);
	}
	
	/**
	 * Setup UI elements
	 */
	protected function setupUI() {
		global $DIC;
		/*
		 * Add breadcrumbs 
		 */
		$DIC['ilLocator']->addRepositoryItems($this->ref_id);
		$DIC['ilLocator']->addItem($this->object->getTitle(),
				$this->ctrl->getLinkTargetByClass(array(
						'ilRepositoryGUI',
						'ilObj' . $this->obj_def->getClassName($this->object->getType()) . 'GUI'
				),
						"",
						$this->ref_id));
		$this->tpl->setLocator();
		
		/*
		 * Add title, description and icon of the current repositoryobject
		 */
		$this->tpl->setTitle($this->object->getTitle());
		$this->tpl->setDescription($this->object->getDescription());
		$this->tpl->setTitleIcon(ilObject::_getIcon($this->object->getId(), 'big', $this->object->getType()));
		
		/*
		 * Create and add backlink
		 */
		$explorer = new ilRepositoryExplorer($this->ref_id);
		$back_link = $explorer->buildLinkTarget($this->ref_id, $this->object->getType());
		$this->tabs->setBackTarget($this->lang->txt("back"),$back_link);
	}
}