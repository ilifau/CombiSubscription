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
		$cmd = $this->ctrl->getCmd('editAssignments');
		switch ($cmd)
		{
			case 'editAssignments':
			case 'saveAssignments':
			case 'saveAssignmentsAsRun':
			case 'setRunAssignments':
			case 'transferAssignments':
			case 'transferAssignmentsConfirmation':
			case 'loadLotLists':
			case 'mailToUsers':
				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}


	/**
	 * Edit the registration of the current user
	 */
	public function editAssignments()
	{
		global $ilUser, $ilToolbar;

		require_once 'Services/UIComponent/Button/classes/class.ilSubmitButton.php';
		$this->parent->checkUnfinishedRuns();

		/** @var ilToolbarGUI $ilToolbar */
		$ilToolbar->setFormAction($this->ctrl->getFormAction($this));
		if ($runs = $this->object->getRunsFinished())
		{
			$options = array();
			foreach ($runs as $index => $run)
			{
				$options[$run->run_id] = $this->object->getRunLabel($index).': '.ilDatePresentation::formatDate($run->run_start);
				$last_run_id = $run->run_id;
			}
			include_once './Services/Form/classes/class.ilSelectInputGUI.php';
			$si = new ilSelectInputGUI($this->plugin->txt('calculation'), "run_id");
			$si->setOptions($options);
			$si->setValue($last_run_id);

			$ilToolbar->addInputItem($si);

			$button = ilSubmitButton::getInstance();
			$button->setCommand('setRunAssignments');
			$button->setCaption($this->plugin->txt('set_run_assignments'), false);
			$ilToolbar->addButtonInstance($button);
			$ilToolbar->addSeparator();
		}

		$button = ilSubmitButton::getInstance();
		$button->setCommand('transferAssignmentsConfirmation');
		$button->setCaption($this->plugin->txt('transfer_assignments'), false);
		$ilToolbar->addButtonInstance($button);

		$this->plugin->includeClass('guis/class.ilCoSubAssignmentsTableGUI.php');
		$table_gui = new ilCoSubAssignmentsTableGUI($this, 'editAssignments');
		$table_gui->prepareData();
		$this->tpl->setContent($table_gui->getHTML());

		$this->showTransferTime();
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

		$this->plugin->includeClass('models/class.ilCoSubRun.php');
		$this->plugin->includeClass('models/class.ilCoSubAssign.php');

		$this->savePostedAssignments();

		$run = new ilCoSubRun();
		$run->obj_id = $this->object->getId();
		$run->run_start = new ilDateTime(time(), IL_CAL_UNIX);
		$run->run_end = new ilDateTime(time(), IL_CAL_UNIX);
		$run->method = 'manual';
		$run->details = sprintf($this->plugin->txt('run_details_manual'), $ilUser->getFullname());
		$run->save();

		$assignments = $this->object->getAssignments();
		foreach ($assignments[0] as $user_id => $items)
		{
			foreach ($items as $item_id => $assign_id)
			{
				$assign = new ilCoSubAssign;
				$assign->obj_id = $this->object->getId();
				$assign->run_id = $run->run_id;
				$assign->user_id = $user_id;
				$assign->item_id = $item_id;
				$assign->save();
			}
		}
		ilUtil::sendSuccess($this->plugin->txt('msg_assignments_saved_as_run'), true);
		$this->ctrl->redirect($this,'editAssignments');
	}

	/**
	 * Save the posted assignments
	 * Helper function for saveAssignments and saveAssignmentsAsRun
	 */
	public function savePostedAssignments()
	{
		foreach ($_POST['assignment'] as $user_id => $new_item_id)
		{
			$found = false;
			foreach ($this->object->getAssignmentsOfUser($user_id, 0) as $item_id => $assign_id)
			{
				if ($item_id == $new_item_id)
				{
					$found = true;
				}
				else
				{
					ilCoSubAssign::_deleteById($assign_id);
				}
			}
			if (!$found)
			{
				$assign = new ilCoSubAssign;
				$assign->obj_id = $this->object->getId();
				$assign->user_id = $user_id;
				$assign->item_id = $new_item_id;
				$assign->save();
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
			$this->plugin->includeClass('models/class.ilCoSubAssign.php');
			ilCoSubAssign::_deleteForObject($this->object->getId(), 0);

			$assignments = $this->object->getAssignments();
			if (is_array($assignments[$_POST['run_id']]))
			{
				foreach ($assignments[$_POST['run_id']] as $user_id => $items)
				{
					foreach ($items as $item_id => $assign_id)
					{
						$assign = new ilCoSubAssign;
						$assign->obj_id = $this->object->getId();
						$assign->user_id = $user_id;
						$assign->item_id = $item_id;
						$assign->save();
					}
				}
			}
			ilUtil::sendSuccess($this->plugin->txt('msg_run_assignments_set'), true);
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
			ilUtil::sendFailure($this->plugin-txt('no_target_objects'),true);
			$this->ctrl->redirect($this,'editAssignments');
		}

		$this->tpl->setContent($conf_gui->getHTML());
		$this->showTransferTime();
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
	 * Show the date when the assignments were already transferred
	 */
	public function showTransferTime()
	{
		$time = $this->object->getClassProperty(get_class($this), 'transfer_time', 0);
		if ($time > 0)
		{
			$date = new ilDateTime($time, IL_CAL_UNIX);
			ilUtil::sendInfo(sprintf($this->plugin->txt('transfer_assignments_time'), ilDatePresentation::formatDate($date)));
		}
	}

	/**
	 * Load the candidates of lot lists
	 */
	public function loadLotLists()
	{
		$this->object->removeUserData();

		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets_obj = new ilCombiSubscriptionTargets($this->object, $this->plugin);
		$targets_obj->loadLotLists();

		ilUtil::sendSuccess($this->plugin->txt('msg_users_loaded_from_lot_lists'), true);
		$this->ctrl->redirect($this,'editAssignments');
	}

}