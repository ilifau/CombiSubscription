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
	 * @param array|null	posted priorities to be set (item_id => priority)
	 */
	public function editRegistration($priorities = null)
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
			$intro = $this->pageInfo($this->object->getExplanation());
		}

		// take the current priorities of hte user if none are posted
		if (!isset($priorities))
		{
			$priorities = $this->object->getPrioritiesOfUser($ilUser->getId());
		}

		$this->plugin->includeClass('guis/class.ilCoSubRegistrationTableGUI.php');
		$table_gui = new ilCoSubRegistrationTableGUI($this, 'editRegistration');
		$table_gui->prepareData(
			$this->object->getItems(),
			$priorities,
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
		$min_choices = $this->object->getMinChoices();
		$has_mc = $method->hasMultipleChoice();
		$has_ec = $method->hasEmptyChoice();
		$max_prio = count($method->getPriorities()) - 1;
		$used_prio = array();
		$choices = array();

		// get and validate the posted choices
		$posted = $this->getPostedPriorities();
		if (count($posted) > 0 && count($posted) < $min_choices)
		{
			ilUtil::sendFailure(sprintf($this->plugin->txt('min_choices_alert'), $min_choices));
			return $this->editRegistration($posted);
		}

		// create choice objects to be saved
		foreach ($this->object->getItems() as $item)
		{
			$priority = $_POST['priority'][$item->item_id];
			if (is_numeric($priority) && $priority >= 0 && $priority <= $max_prio)
			{
				if (isset($used_prio[$priority]) && !$has_mc)
				{
					ilUtil::sendFailure($this->plugin->txt('multiple_choice_alert'));
					return $this->editRegistration($posted);
				}

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

		if (count($used_prio) <= $max_prio && !$has_ec)
		{
			ilUtil::sendFailure($this->plugin->txt('empty_choice_alert'));
			return $this->editRegistration($posted);
		}


		ilCoSubChoice::_deleteForObject($this->object->getId(), $ilUser->getId());
		foreach ($choices as $choice)
		{
			$choice->save();
		}

		$this->plugin->includeClass('class.ilCombiSubscriptionMailNotification.php');
		$notification = new ilCombiSubscriptionMailNotification();
		$notification->setPlugin($this->plugin);
		$notification->setObject($this->object);
		$notification->sendRegistration($ilUser->getId());

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


	/**
	 * Get the posted priority slection of a user
	 * The 'not selected' options are filtered out
	 * @return array	item_id => priority
	 */
	protected function getPostedPriorities()
	{
		$priorities = array();
		foreach ((array) $_POST['priority'] as $item_id => $priority)
		{
			if (is_numeric($item_id) && is_numeric($priority))
			{
				$priorities[$item_id] = $priority;
			}
		}
		return $priorities;
	}
}