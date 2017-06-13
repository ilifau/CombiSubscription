<?php

include_once './Services/Mail/classes/class.ilMailNotification.php';

class ilCombiSubscriptionMailNotification extends ilMailNotification
{
	/** @var  ilCombiSubscriptionPlugin */
	var $plugin;

	/** @var  ilObjCombiSubscription */
	var $object;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @param ilObjCombiSubscription $a_object
	 */
	public function setObject($a_object)
	{
		$this->object = $a_object;
		$this->setObjId($this->object->getId());
		$this->setRefId($this->object->getRefId());
	}

	/**
	 * @param ilCombiSubscriptionPlugin $a_plugin
	 */
	public function setPlugin($a_plugin)
	{
		$this->plugin = $a_plugin;
	}

	/**
	 * Send the notifications
	 * Object and plugin must be set before
	 */
	public function send()
	{
		$users = array_keys($this->object->getPriorities());
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
					$this->appendBody(' - '. $item->title . "\n");
				}
				$this->appendBody("\n");
			}

			$this->appendBody($this->txt('mail_signature')."\n");
			$this->appendBody($this->createPermanentLink());

			$this->getMail()->appendInstallationSignature(true);
			$this->sendMail(array($user_id),array('system'));
		}

	}

	/**
	 * Init language
	 * @param int $a_usr_id
	 */
	protected function initLanguage($a_usr_id)
	{
		$this->language = $this->getUserLanguage($a_usr_id);
		$this->language->loadLanguageModule($this->plugin->getPrefix());
	}


	/**
	 * Get a localized text
	 * @param string $a_keyword
	 * @return string
	 */
	protected function txt($a_keyword)
	{
		return str_replace('\n', "\n",
			$this->getLanguage()->txt(
				$this->plugin->getPrefix() . '_' . $a_keyword,
				$this->plugin->getPrefix()));
	}

}