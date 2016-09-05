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
			case 'addAssignedUsersAsMembers':
			case 'loadLotLists':
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
			$ilToolbar->addFormButton($this->plugin->txt('set_run_assignments'), 'setRunAssignments');
			$ilToolbar->addSeparator();
		}

		// todo: implement confirmations
		//$ilToolbar->addFormButton($this->plugin->txt('add_as_members'), 'addAssignedUsersAsMembers');
		//$ilToolbar->addFormButton($this->plugin->txt('load_lot_lists'), 'loadLotLists');

		$this->plugin->includeClass('guis/class.ilCoSubAssignmentsTableGUI.php');
		$table_gui = new ilCoSubAssignmentsTableGUI($this, 'editAssignments');
		$table_gui->prepareData();
		$this->tpl->setContent($table_gui->getHTML());
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
	 * Add the currently assigned users as members
	 */
	public function addAssignedUsersAsMembers()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets_obj = new ilCombiSubscriptionTargets($this->object, $this->plugin);
		$targets_obj->addAssignedUsersAsMembers();

		ilUtil::sendSuccess($this->plugin->txt('msg_users_added_as_members'), true);
		$this->ctrl->redirect($this,'editAssignments');
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