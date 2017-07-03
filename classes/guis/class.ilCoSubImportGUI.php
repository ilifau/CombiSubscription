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
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this, 'doImport'));
		$this->form->setPreventDoubleSubmission(false);
		$this->form->setTitle($this->plugin->txt('import_data'));
		$this->form->setMultipart(true);

		// import file
		$import_file = new ilFileInputGUI($this->plugin->txt('import_file'), 'import_file');
		//$import_file->setInfo($this->plugin->txt('import_file_info'));
		$import_file->setRequired(true);
		$import_file->setSuffixes(array('xlsx', 'csv'));
		$this->form->addItem($import_file);

		// import mode
		$this->plugin->includeClass('export/class.ilCoSubImport.php');
		$export_mode = new ilRadioGroupInputGUI($this->plugin->txt('import_mode'), 'import_mode');
		$option = new ilRadioOption($this->plugin->txt('import_mode_ass_by_item'), ilCoSubImport::MODE_ASS_BY_ITEM);
		$option->setInfo($this->plugin->txt('import_mode_ass_by_item_info'));
		$export_mode->addOption($option);
		$option = new ilRadioOption($this->plugin->txt('import_mode_ass_by_col'), ilCoSubImport::MODE_ASS_BY_COL);
		$option->setInfo($this->plugin->txt('import_mode_ass_by_col_info'));
		$export_mode->addOption($option);
		$export_mode->setValue($this->object->getPreference('ilCoSubImport', 'import_mode', ilCoSubImport::MODE_ASS_BY_ITEM));
		$this->form->addItem($export_mode);

		$this->form->addCommandButton('doImport', $this->plugin->txt('do_import'));
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
		$this->initImportForm();
		if (!$this->form->checkInput() || !$this->form->hasFileUpload('import_file'))
		{
			$this->showImportForm();
			return;
		}

		// remember the preffered import mode
		$this->object->setPreference('ilCoSubImport', 'export_mode', $this->form->getInput('import_mode'));

		$file = $this->form->getFileUpload('import_file');
		$mode = $this->form->getInput('import_mode');

		$this->plugin->includeClass("export/class.ilCoSubImport.php");
		$import = new ilCoSubImport($this->plugin, $this->object, $mode);
		if ($import->ImportFile($file['tmp_name']))
		{
			// copy the imported assignments to the current ones
			$this->object->copyAssignments($import->getRun()->run_id, 0);

			ilUtil::sendSuccess($this->plugin->txt('import_assignments_finished'), true);
			$this->ctrl->redirectByClass('ilCoSubAssignmentsGUI', 'editAssignments');
		} else
		{
			ilUtil::sendFailure($this->plugin->txt('import_assignments_failed') . '<br />' . $import->getMessage(), true);
			$this->ctrl->redirect($this);
		}
	}
}