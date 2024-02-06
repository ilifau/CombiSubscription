<?php

/**
 * Class for Excel/CSV import of items
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubItemsImportGUI: ilCoSubItemsGUI
 */
class ilCoSubItemsImportGUI extends ilCoSubImportBaseGUI
{
	public function __construct(ilObjCombiSubscriptionGUI $a_parent_gui)
	{
		parent::__construct($a_parent_gui);

		$this->modes = array(
			ilCoSubImport::MODE_ITEMS => array(
				'title' => $this->plugin->txt('import_mode_items'),
				'info' => $this->plugin->txt('import_mode_items_info'),
				'success' => $this->plugin->txt('import_items_finished'),
				'failure' => $this->plugin->txt('import_items_failed'),
				'default' => true
			)
		);
	}
}