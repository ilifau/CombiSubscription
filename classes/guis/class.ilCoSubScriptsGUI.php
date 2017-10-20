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
	/** @var array  mode => ['title' => string, 'info' => string, 'default' => bool] */
	var $modes = array();

	/**
	 * Constructor
	 * @param ilObjCombiSubscriptionGUI $a_parent_gui
	 */
	public function __construct($a_parent_gui)
	{
		parent::__construct($a_parent_gui);
		$this->plugin->includeClass("batch/class.ilCoSubScript.php");

		$this->modes = array(
			ilCoSubScript::MODE_FTP_STRUCTURE => array(
				'title' => 'Struktur für Fertigungstechnisches Praktikum anlegen',
				'info' => 'Legt die Sitzungen und Übungseinheiten an, stellt Vorbedingungen und Lernfortschritt ein',
				'success' => 'Die Struktur wurde angelegt.',
				'failure' => 'Die Struktur konnte nicht anglegt werden!',
				'filename' => 'structure.xlsx',
				'default' => true
			),
			ilCoSubScript::MODE_FTP_ADJUST => array(
				'title' => 'Struktur für Fertigungstechnisches Praktikum anpassen',
				'info' => 'Passt das endedatum der Testobjekte an',
				'success' => 'Die Struktur wurde angepassr.',
				'failure' => 'Die Struktur konnte nicht angepasst werden!',
				'filename' => 'structure.xlsx',
				'default' => true
			)
		);
	}


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
	protected function initImportForm()
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
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
	public function showImportForm()
	{
		$this->initImportForm();
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Do the export
	 */
	public function processFile()
	{
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

		$script = new ilCoSubScript($this->plugin, $this->object, $mode);

		$tempname = ilUtil::ilTempnam();
		if ($script->processFile($file['tmp_name'], $tempname))
		{
			if (is_file($tempname))
			{
				ilUtil::deliverFile($tempname, $this->modes[$mode]['filename']);
			}
			else
			{
				ilUtil::sendSuccess($this->modes[$mode]['success'], true);
				$this->ctrl->returnToParent($this);
			}
		}
		else
		{
			ilUtil::sendFailure($this->modes[$mode]['failure'] . '<br />' . $script->getMessage(), true);
			$this->ctrl->redirect($this);
		}
	}
}