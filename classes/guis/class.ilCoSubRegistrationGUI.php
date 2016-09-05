<?php

/**
 * Registration screen of a combined subscription
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubRegistrationGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubRegistrationGUI extends ilCoSubBaseGUI
{
	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand()
	{
		$cmd = $this->ctrl->getCmd('editRegistration');
		switch ($cmd)
		{
			case 'editRegistration':
			case 'saveRegistration':
			case 'cancelRegistration':
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
	public function editRegistration()
	{
		global $ilUser;

		// check subscription period
		if ($this->object->isBeforeSubscription())
		{
			ilUtil::sendInfo($this->plugin->txt('subscription_period_not_started'));
		}
		elseif ($this->object->isAfterSubscription())
		{
			ilUtil::sendInfo($this->plugin->txt('subscription_period_finished'));
		}


		if ($this->object->getExplanation())
		{
			$intro = '<p>'.$this->object->getExplanation().'</p>';
		}

		$this->plugin->includeClass('guis/class.ilCoSubRegistrationTableGUI.php');
		$table_gui = new ilCoSubRegistrationTableGUI($this, 'editRegistration');
		$table_gui->prepareData(
			$this->object->getItems(),
			$this->object->getPrioritiesOfUser($ilUser->getId()),
			$this->object->getPriorityCounts());
		$this->tpl->setContent($intro . $table_gui->getHTML());
	}


	/**
	 * Save the registration of the current user
	 */
	public function saveRegistration()
	{
		global $ilUser;

		// check subscription period
		if ($this->object->isBeforeSubscription() or $this->object->isAfterSubscription())
		{
			$this->ctrl->redirect($this,'editRegistration');
		}

		$this->plugin->includeClass('models/class.ilCoSubChoice.php');

		$method = $this->object->getMethodObject();
		$has_mc = $method->hasMultipleChoice();
		$max_prio = count($method->getPriorities()) - 1;
		$used_prio = array();
		$choices = array();

		foreach ($this->object->getItems() as $item)
		{
			$priority = $_POST['priority'][$item->item_id];
			if (is_numeric($priority) && $priority >= 0 && $priority <= $max_prio)
			{
				if ($has_mc || !isset($used_prio[$priority]))
				{
					$choice = new ilCoSubChoice();
					$choice->obj_id  = $this->object->getId();
					$choice->user_id = $ilUser->getId();
					$choice->item_id = $item->item_id;
					$choice->priority = $priority;
					$choices[] = $choice;
					$used_prio[$priority] = true;
				}
			}
		}

		ilCoSubChoice::_deleteForObject($this->object->getId(), $ilUser->getId());
		foreach ($choices as $choice)
		{
			$choice->save();
		}

		ilUtil::sendSuccess($this->plugin->txt('msg_registration_saved'), true);
		$this->ctrl->redirect($this,'editRegistration');
	}

	/**
	 * Cancel the registration
	 */
	public function cancelRegistration()
	{
		$this->parent->returnToContainer();
	}
}