<?php

/**
 * Maintenance and statistics of calculation runs
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubRunsGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubRunsGUI extends ilCoSubBaseGUI
{
	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand(): void
	{
		$cmd = $this->ctrl->getCmd('listRuns');
		switch ($cmd)
		{
			case 'listRuns':
			case 'confirmDeleteRuns':
			case 'deleteRuns':
				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}


	/**
	 * List the calculation runs
	 */
	protected function listRuns(): void
	{
		global $ilToolbar;

		$this->parent->checkUnfinishedRuns();

		$table_gui = new ilCoSubRunsTableGUI($this, 'listRuns');
		$table_gui->prepareData($this->object->getRuns());
		$this->tpl->setContent($table_gui->getHTML());
	}


	/**
	 * Delete selected runs
	 */
	protected function confirmDeleteRuns(): void
	{
		global $DIC;

		if (empty($_POST['run_ids']))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure',$this->lng->txt('select_at_least_one_object'), true);
			$this->ctrl->redirect($this,'listRuns');
		}

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this));
		$conf_gui->setHeaderText($this->plugin->txt('confirm_delete_assignments'));
		$conf_gui->setConfirm($this->lng->txt('delete'),'deleteRuns');
		$conf_gui->setCancel($this->lng->txt('cancel'), 'listRuns');

		foreach($_POST['run_ids'] as $run_id)
		{
			$run = ilCoSubRun::_getById($run_id);
			$conf_gui->addItem('run_ids[]', $run_id, ilDatePresentation::formatDate($run->run_start));
		}

		$this->tpl->setContent($conf_gui->getHTML());
	}

	/**
	 * Delete selected runs
	 */
	protected function deleteRuns(): void
	{
		global $DIC;
		if (isset($_POST['run_ids']))
		{
			foreach ($_POST['run_ids'] as $run_id)
			{
				ilCoSubAssign::_deleteForObject($this->object->getId(), $run_id);
				ilCoSubRun::_deleteById($run_id);
			}

			$DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->plugin->txt('msg_assignments_deleted'), true);
		}
		$this->ctrl->redirect($this,'listRuns');
	}


}