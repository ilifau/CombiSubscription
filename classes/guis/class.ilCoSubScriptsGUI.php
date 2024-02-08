<?php

/**
 * GUI class for running specific scripts
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubScriptsGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubScriptsGUI extends ilCoSubBaseGUI
{
	private ilCoSubScript $script;
	/** mode => ['title' => string, 'info' => string, 'default' => bool] */ 
	private array  $modes = array();

	public function __construct(ilObjCombiSubscriptionGUI $a_parent_gui)
	{
		parent::__construct($a_parent_gui);
		$this->script = new ilCoSubScript($this->plugin, $this->object);
		$this->modes = $this->script->getModes();
	}


	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand(): void
	{
		$cmd = $this->ctrl->getCmd('showImportForm');
		switch ($cmd)
		{
			case 'showImportForm':
			case 'processFile':
				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}


	/**
	 * Initialize the form with settings
	 */
	protected function initImportForm(): void
	{
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this, 'doImport'));
		$this->form->setPreventDoubleSubmission(false);
		$this->form->setTitle($this->plugin->txt('run_scripts'));
		$this->form->setMultipart(true);

		// import file
		$import_file = new ilFileInputGUI($this->plugin->txt('import_file'), 'import_file');
		//$import_file->setInfo($this->plugin->txt('import_file_info'));
		$import_file->setRequired(true);
		$import_file->setSuffixes(array('xlsx'));
		$this->form->addItem($import_file);

		// import mode
		$import_mode = new ilRadioGroupInputGUI($this->plugin->txt('import_mode'), 'import_mode');
		$default_mode = null;
		foreach ($this->modes as $mode => $details)
		{
			$option = new ilRadioOption($details['title'], $mode);
			$option->setInfo($details['info']);
			$default_mode = $details['default'] ? $mode : $default_mode;
			$import_mode->addOption($option);
		}
		$import_mode->setValue($this->object->getPreference(get_class($this), 'import_mode', $default_mode));
		$this->form->addItem($import_mode);

		$this->form->addCommandButton('processFile', $this->plugin->txt('run'));
	}

	/**
	 * Show the form with export settings
	 */
	public function showImportForm(): void
	{
		$this->initImportForm();
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Do the export
	 */
	public function processFile(): void
	{
		global $DIC;

		$this->initImportForm();
		if (!$this->form->checkInput() || !$this->form->hasFileUpload('import_file'))
		{
			$this->showImportForm();
			return;
		}

		// remember the preffered import mode
		$this->object->setPreference(get_class($this), 'import_mode', $this->form->getInput('import_mode'));

		$file = $this->form->getFileUpload('import_file');
		$mode = $this->form->getInput('import_mode');

		$this->script->setMode($mode);

		$tempname = ilFileUtils::ilTempnam();
		if ($this->script->processFile($file['tmp_name'], $tempname))
		{
			if (is_file($tempname))
			{
				ilFileDelivery::deliverFileLegacy($tempname, $this->modes[$mode]['filename']);
			}
			else
			{
				$DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->modes[$mode]['success'], true);
				$this->ctrl->returnToParent($this);
			}
		}
		else
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->modes[$mode]['failure'] . '<br />' . $this->script->getMessage(), true);
			$this->ctrl->redirect($this);
		}
	}
}