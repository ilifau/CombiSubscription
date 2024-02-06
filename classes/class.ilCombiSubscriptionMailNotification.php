<?php

class ilCombiSubscriptionMailNotification extends ilMailNotification
{
	private ilCombiSubscriptionPlugin $plugin;
	private ilObjCombiSubscription $object;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	public function setObject(ilObjCombiSubscription $a_object): void
	{
		$this->object = $a_object;
		$this->setObjId($this->object->getId());
		$this->setRefId($this->object->getRefId());
	}

	public function setPlugin(ilCombiSubscriptionPlugin $a_plugin): void
	{
		$this->plugin = $a_plugin;
	}

	/**
	 * Send a registration confirmation to the user
	 */
	public function sendRegistration(int $user_id, bool $by_admin = false): void
	{
		global $ilUser;

		$priorities = $this->object->getPrioritiesOfUser($user_id);
		$names = $this->object->getMethodObject()->getPriorities();

		/** @var ilCoSubItem[] $items */
		$items = array();
		foreach ($this->object->getItems() as $item)
		{
			$items[$item->item_id] = $item;
		}
		$this->initLanguage($user_id);
		$this->initMail();
		$this->setSubject(
			sprintf($this->txt('mail_registration_subject'),$this->getObjectTitle(true))
		);
		$this->setBody(ilMail::getSalutation($user_id, $this->getLanguage()));
		$this->appendBody("\n\n");


		if ($by_admin)
		{
			$this->appendBody(sprintf($this->txt('mail_registration_by_admin'), $ilUser->getFullname())."\n\n");
		}
		else
		{
			$this->appendBody($this->txt('mail_registration_own')."\n\n");
		}

		if (empty($priorities))
		{
			$this->appendBody($this->txt('mail_registration_no_choice')."\n\n");
		}
		else
		{
			$this->appendBody($this->txt('mail_registration_choices')."\n\n");
			foreach ($priorities as $item_id => $priority)
			{
				$item = $items[$item_id];
				$item_desc =  $item->title;
				if ($item->getPeriodInfo()) {
					$item_desc .= ' '. $item->getPeriodInfo();
				}
				$this->appendBody(' - '. $item_desc .' (' . $names[$priority] .')'. "\n");
			}
			$this->appendBody("\n");
		}

		$this->appendBody($this->txt('mail_signature')."\n");
		$this->appendBody($this->createPermanentLink());

		$this->getMail()->appendInstallationSignature(true);
		$this->sendMail(array($user_id),array('system'));
	}

	/**
	 * Send the notifications about Assignments
	 * Object and plugin must be set before
	 * array 	$removedConflicts  user_id => obj_id => item
     * array     $users  list of specific users to treat: user_id[]
	 */
	public function sendAssignments(array $removedConflicts = [], array $users = []): void
	{

		/** @var ilAccessHandler $ilAccess */
		global $ilAccess;

		if (empty($users))
        {
            $users = array_keys($this->object->getPriorities());
        }
		$assignments = $this->object->getAssignments();

		/** @var ilCoSubItem[] $items */
		$items = array();
		foreach ($this->object->getItems() as $item)
		{
			$items[$item->item_id] = $item;
		}

		foreach($users as $user_id)
		{
			$this->initLanguage($user_id);
			$this->initMail();
			$this->setSubject(
				sprintf($this->txt('mail_notify_subject'),$this->getObjectTitle(true))
			);
			$this->setBody(ilMail::getSalutation($user_id, $this->getLanguage()));
			$this->appendBody("\n\n");

			if (empty($assignments[0][$user_id]))
			{
				$this->appendBody($this->txt('mail_notify_no_assignment')."\n\n");
			}
			else
			{
				$this->appendBody($this->txt('mail_notify_assignments')."\n\n");
				foreach ($assignments[0][$user_id] as $item_id => $assign_id)
				{
					$item = $items[$item_id];
					$item_desc =  $item->title;
					if ($item->getPeriodInfo()) {
						$item_desc .= ' '. $item->getPeriodInfo();
					}
					$this->appendBody(' - '. $item_desc . "\n");
				}
				$this->appendBody("\n");
			}

			if(!empty($removedConflicts[$user_id]))
			{
				$this->appendBody($this->txt('mail_notify_removed_conflicts')."\n\n");

				foreach($removedConflicts[$user_id] as $obj_id => $conflictItems)
				{
					$title = ilObject::_lookupTitle($obj_id);
					foreach(ilObject::_getAllReferences($obj_id) as $ref_id)
					{
						if ($ilAccess->checkAccessOfUser($user_id, 'visible', '', $ref_id))
						{
							$title = '<a href="' . ilLink::_getStaticLink($ref_id, 'xcos') . '">' . $title .'/<a>';
							break;
						}
					}
					$this->appendBody($title."\n");

					/** @var ilCoSubItem $conflictItem */
					foreach ($conflictItems as $conflictItem)
					{
						$this->appendBody(' * ' . $conflictItem->title . ' '. $conflictItem->getPeriodInfo(). "\n");
					}
				}
			}

			$this->appendBody("\n\n" .$this->txt('mail_signature')."\n");
			$this->appendBody($this->createPermanentLink());

			$this->getMail()->appendInstallationSignature(true);
			$this->sendMail(array($user_id),array('system'));
		}

	}


	/**
	 * Send the notifications about being removed
	 * Object and plugin must be set before
	 * int[] $a_users
	 */
	public function sendRemoval(array $a_users): void
	{
		foreach($a_users as $user_id)
		{
			$this->initLanguage($user_id);
			$this->initMail();
			$this->setSubject(
				sprintf($this->txt('mail_removal_subject'),$this->getObjectTitle(true))
			);
			$this->setBody(ilMail::getSalutation($user_id, $this->getLanguage()));
			$this->appendBody("\n\n");
			$this->appendBody($this->txt('mail_removal_message')."\n\n");
			$this->appendBody($this->txt('mail_signature')."\n");
			$this->appendBody($this->createPermanentLink());

			$this->getMail()->appendInstallationSignature(true);
			$this->sendMail(array($user_id),array('system'));
		}

	}

	/**
	 * Init language
	 */
	protected function initLanguage(int $a_usr_id): void
	{
		$this->language = $this->getUserLanguage($a_usr_id);
		$this->language->loadLanguageModule($this->plugin->getPrefix());
	}


	/**
	 * Get a localized text
	 */
	protected function txt(string $a_keyword): string
	{
		return str_replace('\n', "\n",
			$this->getLanguage()->txt(
				$this->plugin->getPrefix() . '_' . $a_keyword,
				$this->plugin->getPrefix()));
	}

}