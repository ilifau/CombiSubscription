<?php

/**
 * Assignment Screen of a combined subscription
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 *
 * @ilCtrl_isCalledBy ilCoSubAssignmentsGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubAssignmentsGUI extends ilCoSubUserManagementBaseGUI
{
	/** @var string command to show the list of users */
	protected $cmdUserList = 'editAssignments';

	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand()
	{
		$next_class = $this->ctrl->getNextClass();
		switch ($next_class)
		{
			// assignments import
			case 'ilcosubassignmentsimportgui':
				$this->plugin->includeClass('abstract/class.ilCoSubImportBaseGUI.php');
				$this->plugin->includeClass('guis/class.ilCoSubAssignmentsImportGUI.php');
				$this->ctrl->setReturn($this, 'editAssignments');
				$this->tabs->activateSubTab('import');
				$this->ctrl->forwardCommand(new ilCoSubAssignmentsImportGUI($this->parent));
				return;
		}

		$cmd = $this->ctrl->getCmd('editAssignments');
		switch ($cmd)
		{
//			case 'autoProcess': // testing only
			case 'editAssignments':
			case 'calculateAssignments':
			case 'calculateAssignmentsConfirmation':
			case 'saveAssignments':
			case 'saveAssignmentsAsRun':
			case 'setRunAssignments':
			case 'transferAssignments':
			case 'transferAssignmentsConfirmation':
			case 'notifyAssignments':
			case 'notifyAssignmentsConfirmation':
			case 'mailToUsers':
			case 'fixUsers':
			case 'fixUsersConfirmation':
			case 'unfixUsers':
			case 'unfixUsersConfirmation':
			case 'removeUsers':
			case 'removeUsersConfirmation':

				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}

	/**
	 * Set the toolbar for the assignments screen
	 */
	protected function setAssignmentsToolbar()
	{
		global $ilToolbar;

		require_once 'Services/UIComponent/Button/classes/class.ilSubmitButton.php';

		/** @var ilToolbarGUI $ilToolbar */
		$ilToolbar->setFormAction($this->ctrl->getFormAction($this));

		if ($this->object->getMethodObject()->isActive())
		{
			$button = ilSubmitButton::getInstance();
			$button->setCommand('calculateAssignmentsConfirmation');
			$button->setCaption($this->plugin->txt('calculate_assignments'), false);
			$ilToolbar->addButtonInstance($button);
			$ilToolbar->addSeparator();
		}

		if ($runs = $this->object->getRunsFinished())
		{
			$options = array();
			$source_run = $this->object->getClassProperty(get_class($this), 'source_run', 0);
			$options[0] = $this->lng->txt('please_select');
			foreach ($runs as $index => $run)
			{
				$options[$run->run_id] = $this->object->getRunLabel($index).': '.ilDatePresentation::formatDate($run->run_start);
			}
			include_once './Services/Form/classes/class.ilSelectInputGUI.php';
			$si = new ilSelectInputGUI($this->plugin->txt('saved_label'), "run_id");
			$si->setOptions($options);
			$si->setValue($source_run);

			$ilToolbar->addInputItem($si, true);

			$button = ilSubmitButton::getInstance();
			$button->setCommand('setRunAssignments');
			$button->setCaption($this->plugin->txt('set_assignments'), false);
			$ilToolbar->addButtonInstance($button);
			$ilToolbar->addSeparator();
		}

		$button = ilSubmitButton::getInstance();
		$button->setCommand('transferAssignmentsConfirmation');
		$button->setCaption($this->plugin->txt('transfer_assignments'), false);
		$ilToolbar->addButtonInstance($button);

		$button = ilSubmitButton::getInstance();
		$button->setCommand('notifyAssignmentsConfirmation');
		$button->setCaption($this->plugin->txt('notify_assignments'), false);
		$ilToolbar->addButtonInstance($button);

		// for testing purposes
//		$button = ilSubmitButton::getInstance();
//		$button->setCommand('autoProcess');
//		$button->setCaption($this->plugin->txt('auto_process'), false);
//		$ilToolbar->addButtonInstance($button);
	}

	/**
	 * Edit the registration of the current user
	 */
	public function editAssignments()
	{
		$this->parent->checkUnfinishedRuns();
		$this->setAssignmentsToolbar();

		$this->plugin->includeClass('guis/class.ilCoSubAssignmentsTableGUI.php');
		$table_gui = new ilCoSubAssignmentsTableGUI($this, 'editAssignments');
		$table_gui->prepareData();

		$description = $this->plugin->txt('assignments_description'). ' '. $this->plugin->txt('assignments_description_runs');
		$this->tpl->setContent($this->pageInfo($description).$table_gui->getHTML());

		$this->showInfo();
	}

	/**
	 * Confirm the transfer of assignments to target objects
	 */
	public function calculateAssignmentsConfirmation()
	{
		require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this,'calculateAssignments'));
		$conf_gui->setHeaderText($this->plugin->txt('calculate_assignments_confirmation')
			. $this->messageDetails($this->plugin->txt('calculate_assignments_confirmation_details')));
		$conf_gui->setConfirm($this->plugin->txt('calculate_assignments'),'calculateAssignments');
		$conf_gui->setCancel($this->lng->txt('cancel'),'editAssignments');

		$this->tpl->setContent($conf_gui->getHTML());
		$this->showInfo();
	}


	/**
	 * Calculate new assignments
	 */
	protected function calculateAssignments()
	{
		if (!$this->object->getMethodObject()->isActive())
		{
			ilUtil::sendFailure($this->plugin->txt('msg_method_not_active'),true);
		}
		else
		{
			$this->plugin->includeClass('models/class.ilCoSubRun.php');
			$run = new ilCoSubRun;
			$run->obj_id = $this->object->getId();
			$run->method = $this->object->getMethodObject()->getId();
			$run->save();

			if ($this->object->getMethodObject()->calculateAssignments($run))
			{
				if ($run->run_end)
				{
					$letter = $this->object->getRunLabel(count($this->object->getRuns()) -1);
					$date = ilDatePresentation::formatDate($run->run_start);

					// copy the calculated assignments of the run to the current assignments
					$this->object->copyAssignments($run->run_id, 0);
					$this->object->setClassProperty(get_class($this), 'source_run', $run->run_id);
					ilUtil::sendSuccess(sprintf($this->plugin->txt('msg_calculation_finished'), $letter, $date), true);
				}
				else
				{
					ilUtil::sendInfo($this->plugin->txt('msg_calculation_started'), true);
				}
			}
			else
			{
				ilUtil::sendFailure($this->plugin->txt('msg_calculation_start_failed')
					.'<br />'.$this->object->getMethodObject()->getError(), true);
			}
		}
		$this->ctrl->redirect($this,'editAssignments');
	}


	/**
	 * Save the assignments of the displayed users
	 */
	public function saveAssignments()
	{
		$this->savePostedAssignments();
		ilUtil::sendSuccess($this->plugin->txt('msg_assignments_saved'), true);
		$this->ctrl->redirect($this,'editAssignments');
	}


	/**
	 * Save the manual assignments as a new calculation run
	 */
	public function saveAssignmentsAsRun()
	{
		/** @var ilObjUser $ilUser */
		global $ilUser;


		$this->savePostedAssignments();

		$this->plugin->includeClass('models/class.ilCoSubRun.php');
		$run = new ilCoSubRun();
		$run->obj_id = $this->object->getId();
		$run->run_start = new ilDateTime(time(), IL_CAL_UNIX);
		$run->run_end = new ilDateTime(time(), IL_CAL_UNIX);
		$run->method = 'manual';
		$run->details = sprintf($this->plugin->txt('run_details_manual'), $ilUser->getFullname());
		$run->save();

		$this->object->copyAssignments(0, $run->run_id);
		$this->object->setClassProperty(get_class($this), 'source_run', $run->run_id);

		$letter = $this->object->getRunLabel(count($this->object->getRuns()) -1);
		$date = ilDatePresentation::formatDate($run->run_start);
		ilUtil::sendSuccess(sprintf($this->plugin->txt('msg_assignments_saved_as_run'), $date, $letter), true);
		$this->ctrl->redirect($this,'editAssignments');
	}

	/**
	 * Save the posted assignments
	 * Helper function for saveAssignments and saveAssignmentsAsRun
	 */
	public function savePostedAssignments()
	{
		foreach ($this->object->getUsers() as $user_id => $userObj)
		{
			if ($userObj->is_fixed || !in_array($user_id, (array) $_POST['page_ids']))
			{
				continue;
			}

			$assignments = $this->object->getAssignmentsOfUser($user_id, 0);

			$new_item_ids = (array) $_POST['assignment'][$user_id];
			$old_item_ids = array_keys($assignments);

			foreach ($assignments as $item_id => $assign_id)
			{
				if (!in_array($item_id, $new_item_ids))
				{
					ilCoSubAssign::_deleteById($assign_id);
				}
			}

			foreach ($new_item_ids as $new_item_id)
			{
				if (!empty((int) $new_item_id) && !in_array((int) $new_item_id, $old_item_ids))
				{
					$assign = new ilCoSubAssign;
					$assign->obj_id = $this->object->getId();
					$assign->user_id = $user_id;
					$assign->item_id = $new_item_id;
					$assign->save();
				}
			}
		}
	}

	/**
	 * Set the assignments from a run
	 */
	public function setRunAssignments()
	{
		if (!empty($_POST['run_id']))
		{
			$this->object->copyAssignments($_POST['run_id'], 0);
			$this->object->setClassProperty(get_class($this), 'source_run', $_POST['run_id']);
			ilUtil::sendSuccess($this->plugin->txt('msg_assignments_set'), true);
		}
		$this->ctrl->redirect($this,'editAssignments');
	}

	/**
	 * Confirm the transfer of assignments to target objects
	 */
	public function transferAssignmentsConfirmation()
	{
		require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
		require_once('Services/Locator/classes/class.ilLocatorGUI.php');

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this,'transferAssignments'));
		$conf_gui->setHeaderText($this->plugin->txt('transfer_assignments_confirmation'));
		$conf_gui->setConfirm($this->plugin->txt('transfer_assignments'),'transferAssignments');
		$conf_gui->setCancel($this->lng->txt('cancel'),'editAssignments');

		$count = 0;
		foreach ($this->object->getItems() as $item)
		{
			$locator = new ilLocatorGUI();
			if (!empty($item->target_ref_id))
			{
				$locator->addContextItems($item->target_ref_id);
				$count++;
			}
			$conf_gui->addItem('ref_id', $item->target_ref_id, $locator->getHTML());
		}

		if ($count == 0)
		{
			ilUtil::sendFailure($this->plugin->txt('no_target_objects'),true);
			$this->ctrl->redirect($this,'editAssignments');
		}

		$this->tpl->setContent($conf_gui->getHTML());
		$this->showInfo();
	}


	/**
	 * Add the assigned users as members
	 * This fixes the assigned users and removes their conflicting subscriptions in other objects
	 * The assigned users are notified
	 */
	public function transferAssignments()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets_obj = new ilCombiSubscriptionTargets($this->object, $this->plugin);
		$targets_obj->filterUntrashedTargets();
		$targets_obj->addAssignedUsersAsMembers();
		$targets_obj->addNonAssignedUsersAsSubscribers();

		$this->object->fixAssignedUsers();
		$removedConflicts = $this->object->removeConflicts();

		$this->plugin->includeClass('class.ilCombiSubscriptionMailNotification.php');
		$notification = new ilCombiSubscriptionMailNotification();
		$notification->setPlugin($this->plugin);
		$notification->setObject($this->object);
		$notification->sendAssignments($removedConflicts);

		$this->object->setClassProperty(get_class($this), 'transfer_time', time());
		$this->ctrl->redirect($this,'editAssignments');
	}


	/**
	 * Confirm the transfer of assignments to target objects
	 */
	public function notifyAssignmentsConfirmation()
	{
		require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this,'notifyAssignments'));
		$conf_gui->setHeaderText($this->plugin->txt('notify_assignments_confirmation'));
		$conf_gui->setConfirm($this->plugin->txt('notify_assignments'),'notifyAssignments');
		$conf_gui->setCancel($this->lng->txt('cancel'),'editAssignments');

		$this->tpl->setContent($conf_gui->getHTML());
		$this->showInfo();
	}

	/**
	 * Notify the users about their Assignments
	 * This fixes the assigned users and removes their conflicting subscriptions in other objects
	 */
	public function notifyAssignments()
	{
		$this->object->fixAssignedUsers();
		$removedConflicts = $this->object->removeConflicts();

		$this->plugin->includeClass('class.ilCombiSubscriptionMailNotification.php');
		$notification = new ilCombiSubscriptionMailNotification();
		$notification->setPlugin($this->plugin);
		$notification->setObject($this->object);
		$notification->sendAssignments($removedConflicts);

		$this->object->setClassProperty(get_class($this), 'notify_time', time());
		$this->ctrl->redirect($this,'editAssignments');
	}

	/**
	 * Manual start of auto processing (for testing)
	 */
	public function autoProcess()
	{
		$this->object->handleAutoProcess();
		$this->ctrl->redirect($this,'editAssignments');
	}
}