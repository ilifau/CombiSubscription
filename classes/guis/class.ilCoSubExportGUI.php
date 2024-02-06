<?php

/**
 * Excel/CSV export of priorities
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubExportGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubExportGUI extends ilCoSubBaseGUI 
{
	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand(): void
	{
		$cmd = $this->ctrl->getCmd('showExportForm');
		switch ($cmd)
		{
			case 'showExportForm':
			case 'doExport':
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
	protected function initExportForm(): void
	{
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this, 'doExport'));
		$this->form->setPreventDoubleSubmission(false);
		$this->form->setTitle($this->plugin->txt('export_data'));

		// export mode
		$export_mode = new ilRadioGroupInputGUI($this->plugin->txt('export_mode'), 'export_mode');
		$option = new ilRadioOption($this->plugin->txt('export_mode_reg_by_item'), ilCoSubExport::MODE_REG_BY_ITEM);
		$option->setInfo($this->plugin->txt('export_mode_reg_by_item_info'));
		$export_mode->addOption($option);
		$option = new ilRadioOption($this->plugin->txt('export_mode_reg_by_prio'), ilCoSubExport::MODE_REG_BY_PRIO);
		$option->setInfo($this->plugin->txt('export_mode_reg_by_prio_info'));
		$export_mode->addOption($option);
		$option = new ilRadioOption($this->plugin->txt('export_mode_ass_by_item'), ilCoSubExport::MODE_ASS_BY_ITEM);
		$option->setInfo($this->plugin->txt('export_mode_ass_by_item_info'));
		$export_mode->addOption($option);
        $option = new ilRadioOption($this->plugin->txt('export_mode_raw_data'), ilCoSubExport::MODE_RAW_DATA);
        $option->setInfo($this->plugin->txt('export_mode_raw_data_info'));
        $export_mode->addOption($option);
		$export_mode->setValue($this->object->getPreference('ilCoSubExport', 'export_mode', ilCoSubExport::MODE_REG_BY_ITEM));
		$this->form->addItem($export_mode);

		// export type
		$export_type = new ilRadioGroupInputGUI($this->plugin->txt('export_type'), 'export_type');
		$option = new ilRadioOption($this->plugin->txt('export_type_excel'), ilCoSubExport::TYPE_EXCEL);
		$export_type->addOption($option);
		$option = new ilRadioOption($this->plugin->txt('export_type_csv'), ilCoSubExport::TYPE_CSV);
		$export_type->addOption($option);
		$export_type->setValue($this->object->getPreference('ilCoSubExport', 'export_type', ilCoSubExport::TYPE_EXCEL));
		$this->form->addItem($export_type);

		$this->form->addCommandButton('doExport', $this->plugin->txt('do_export'));
	}

	/**
	 * Show the form with export settings
	 */
	public function showExportForm(): void
	{
		$this->initExportForm();
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Do the export
	 */
	public function doExport(): void
	{
		$this->initExportForm();
		if (!$this->form->checkInput())
		{
			$this->showExportForm();
			return;
		}

		$this->object->setPreference('ilCoSubExport', 'export_mode', $this->form->getInput('export_mode'));
		$this->object->setPreference('ilCoSubExport', 'export_type', $this->form->getInput('export_type'));

		$type = $_POST['export_type'];
		switch ($_POST['export_type'])
		{
			case ilCoSubExport::TYPE_CSV:
				$suffix = ".csv";
				break;

			case ilCoSubExport::TYPE_EXCEL:
			default;
				$suffix = ".xlsx";
				break;
		}

		$mode = $_POST['export_mode'];
		switch ($mode)
		{
            case ilCoSubExport::MODE_RAW_DATA:
                $name = ilUtil::getASCIIFilename($this->object->getTitle());
                $suffix = '.zip';
                break;

			case ilCoSubExport::MODE_REG_BY_ITEM:
			case ilCoSubExport::MODE_REG_BY_PRIO:
			default:
				$name = 'registrations';
		}

		// create and send the export file
		$export = new ilCoSubExport($this->plugin, $this->object, $type, $mode);
        $file = $export->buildExportFile();


		if (is_file($file))
		{
			ilUtil::deliverFile($file, basename($file));
		}
		else
		{
			ilUtil::sendFailure($this->plugin->txt('export_not_found'), true);
			$this->ctrl->redirect($this);
		}
	}
}