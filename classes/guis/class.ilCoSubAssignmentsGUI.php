<?php

/**
 * Assignment Screen of a combined subscription
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubAssignmentsGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubAssignmentsGUI extends ilCoSubBaseGUI
{
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
			$last_run_id = 0;
			foreach ($runs as $index => $run)
			{
				$options[$run->run_id] = $this->object->getRunLabel($index).': '.ilDatePresentation::formatDate($run->run_start);
				$last_run_id = $run->run_id;
			}
			include_once './Services/Form/classes/class.ilSelectInputGUI.php';
			$si = new ilSelectInputGUI($this->plugin->txt('saved_label'), "run_id");
			$si->setOptions($options);
			$si->setValue($last_run_id);

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
			if ($userObj->is_fixed)
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
	 * Add the currently assigned users as members
	 */
	public function transferAssignments()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets_obj = new ilCombiSubscriptionTargets($this->object, $this->plugin);
		$targets_obj->addAssignedUsersAsMembers();
		$targets_obj->addNonAssignedUsersAsSubscribers();

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
	 */
	public function notifyAssignments()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionMailNotification.php');
		$notification = new ilCombiSubscriptionMailNotification();
		$notification->setPlugin($this->plugin);
		$notification->setObject($this->object);
		$notification->sendAssignments();

		$this->object->setClassProperty(get_class($this), 'notify_time', time());
		$this->ctrl->redirect($this,'editAssignments');
	}

	/**
	 * Show info about date when the assignments were already transferred ure users were notified
	 */
	public function showInfo()
	{
		$messages = array();
		$transfer_time = $this->object->getClassProperty(get_class($this), 'transfer_time', 0);
		if ($transfer_time > 0)
		{
			$date = new ilDateTime($transfer_time, IL_CAL_UNIX);
			$messages[] = sprintf($this->plugin->txt('transfer_assignments_time'), ilDatePresentation::formatDate($date));
		}
		$notify_time = $this->object->getClassProperty(get_class($this), 'notify_time', 0);
		if ($notify_time > 0)
		{
			$date = new ilDateTime($notify_time, IL_CAL_UNIX);
			$messages[] = sprintf($this->plugin->txt('notify_assignments_time'), ilDatePresentation::formatDate($date));
		}

		if (!empty($messages))
		{
			ilUtil::sendInfo(implode('<br />', $messages));
		}
	}


	/**
	 * Send an e-mail to selected users
	 */
	public function mailToUsers()
	{
		if (empty($_POST['ids']))
		{
			ilUtil::sendFailure($this->lng->txt("no_checkbox"), true);
			$this->ctrl->redirect($this, 'editAssignments');
		}
		$rcps = array();
		foreach($_POST['ids'] as $usr_id)
		{
			$rcps[] = ilObjUser::_lookupLogin($usr_id);
		}

		require_once 'Services/Mail/classes/class.ilMailFormCall.php';
		require_once 'Services/Link/classes/class.ilLink.php';
		ilMailFormCall::setRecipients($rcps);

		$signature = "\n\n" . $this->plugin->txt('mail_signature') . "\n" . ilLink::_getStaticLink($this->object->getRefId());

		$target = ilMailFormCall::getRedirectTarget(
			$this,
			'editAssignments',
			array(),
			array('type' => 'new', 'sig' => rawurlencode(base64_encode($signature))));

		ilUtil::redirect($target);
	}


	/**
	 * Confirm the fixation of users
	 */
	public function fixUsersConfirmation()
	{
		if (empty($_POST['ids']))
		{
			ilUtil::sendFailure($this->lng->txt("no_checkbox"), true);
			$this->ctrl->redirect($this, 'editAssignments');
		}

		require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this,'fixUsers'));
		$conf_gui->setHeaderText($this->plugin->txt('fix_users_confirmation'));
		$conf_gui->setConfirm($this->plugin->txt('fix_users'),'fixUsers');
		$conf_gui->setCancel($this->lng->txt('cancel'),'editAssignments');

		foreach ($this->object->getUserDetails($_POST['ids']) as $user_id => $details)
		{
			$conf_gui->addItem('ids[]', $user_id, $details['showname'], ilUtil::getImagePath('icon_usr.svg'));

		}
		$this->tpl->setContent($conf_gui->getHTML());
		$this->showInfo();
	}

	/**
	 * Fix the assignments of selected users
	 */
	public function fixUsers()
	{
		if (empty($_POST['ids']))
		{
			ilUtil::sendFailure($this->lng->txt("no_checkbox"), true);
			$this->ctrl->redirect($this, 'editAssignments');
		}

		foreach($this->object->getUsers($_POST['ids']) as $user_id => $userObj)
		{
			$userObj->is_fixed = true;
			$userObj->save();
		}

		ilUtil::sendSuccess($this->plugin->txt('fix_users_done'), true);
		$this->ctrl->redirect($this, 'editAssignments');
	}

	/**
	 * Confirm the fixation of users
	 */
	public function unfixUsersConfirmation()
	{
		if (empty($_POST['ids']))
		{
			ilUtil::sendFailure($this->lng->txt("no_checkbox"), true);
			$this->ctrl->redirect($this, 'editAssignments');
		}

		require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this,'unfixUsers'));
		$conf_gui->setHeaderText($this->plugin->txt('unfix_users_confirmation'));
		$conf_gui->setConfirm($this->plugin->txt('unfix_users'),'unfixUsers');
		$conf_gui->setCancel($this->lng->txt('cancel'),'editAssignments');

		foreach ($this->object->getUserDetails($_POST['ids']) as $user_id => $details)
		{
			$conf_gui->addItem('ids[]', $user_id, $details['showname'], ilUtil::getImagePath('icon_usr.svg'));

		}
		$this->tpl->setContent($conf_gui->getHTML());
		$this->showInfo();
	}

	/**
	 * Fix the assignments of selected users
	 */
	public function unfixUsers()
	{
		if (empty($_POST['ids']))
		{
			ilUtil::sendFailure($this->lng->txt("no_checkbox"), true);
			$this->ctrl->redirect($this, 'editAssignments');
		}

		foreach($this->object->getUsers($_POST['ids']) as $user_id => $userObj)
		{
			$userObj->is_fixed = false;
			$userObj->save();
		}

		ilUtil::sendSuccess($this->plugin->txt('unfix_users_done'), true);
		$this->ctrl->redirect($this, 'editAssignments');
	}

}