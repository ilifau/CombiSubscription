<?php

/**
 * Excel/CSV import of assignments
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubImportGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubImportGUI extends ilCoSubBaseGUI
{
	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand()
	{
		$cmd = $this->ctrl->getCmd('showImportForm');
		switch ($cmd)
		{
			case 'showImportForm':
			case 'doImport':
				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}


	/**
	 * Initialize the form with export settings
	 */
	protected function initImportForm()
	{
	}

	/**
	 * Show the form with export settings
	 */
	public function showImportForm()
	{
		$this->initImportForm();
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Do the export
	 */
	public function doImport()
	{
	}
}