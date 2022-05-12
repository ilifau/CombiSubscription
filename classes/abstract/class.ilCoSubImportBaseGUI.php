<?php

/**
 * Base class for Excel/CSV import
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
abstract class ilCoSubImportBaseGUI extends ilCoSubBaseGUI
{

	/** @var array  mode => ['title' => string, 'info' => string, 'default' => bool] */
	var $modes = array();

    /** @var bool add a comment input field to the import form */
    protected $add_comment = false;

	/**
	 * Constructor
	 * @param ilObjCombiSubscriptionGUI $a_parent_gui
	 */
	public function __construct($a_parent_gui)
	{
		parent::__construct($a_parent_gui);
		$this->plugin->includeClass('batch/class.ilCoSubImport.php');
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

        if ($this->add_comment) {
            $comment = new ilTextInputGUI($this->plugin->txt('import_comment'), 'comment');
            $this->form->addItem($comment);
        }

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
		$this->object->setPreference(get_class($this), 'import_mode', $this->form->getInput('import_mode'));

		$file = $this->form->getFileUpload('import_file');
		$mode = $this->form->getInput('import_mode');
        $comment = ($this->add_comment ?  $this->form->getInput('import_comment') : '');

		$this->plugin->includeClass("batch/class.ilCoSubImport.php");
		$import = new ilCoSubImport($this->plugin, $this->object, $mode, $comment);

		if ($import->ImportFile($file['tmp_name']))
		{
			ilUtil::sendSuccess($this->modes[$mode]['success'], true);
			$this->ctrl->returnToParent($this);
		}
		else
		{
			ilUtil::sendFailure($this->modes[$mode]['failure'] . '<br />' . $import->getMessage(), true);
			$this->ctrl->redirect($this);
		}
	}
}