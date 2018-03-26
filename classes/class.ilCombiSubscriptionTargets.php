<?php

/**
 * Target management for combined subscriptions
 * All course/group related functions should go here
 */
class ilCombiSubscriptionTargets
{
	/** @var  ilObjCombiSubscription */
	protected $object;

	/** @var ilCombiSubscriptionPlugin  */
	protected $plugin;

	/** @var  ilCoSubItem[] (indexed by item_id) */
	protected $items = array();

	/**
	 * @var array [['grouping' => ilObjCourseGrouping, 'conditions' => array], ...]
	 */
	protected $groupings = null;

	/**
	 * Constructor
	 * @param ilObjCombiSubscription        $a_object
	 * @param ilCombiSubscriptionPlugin     $a_plugin
	 */
	public function __construct($a_object, $a_plugin)
	{
		$this->object = $a_object;
		$this->plugin = $a_plugin;

		$this->items = $this->object->getItems();

		$this->plugin->includeClass('models/class.ilCoSubTargetsConfig.php');
	}


	/**
	 * Check if a target type supports a subscriptionPeriod
	 * @param string $a_type
	 * @return bool
	 */
	public function hasSubscriptionPeriod($a_type)
	{
		return in_array($a_type, array('crs', 'grp', 'auto'));
	}

	/**
	 * Check if a target type supports minimum subscriptions
	 * @param string $a_type
	 * @return bool
	 */
	public function hasMinSubscriptions($a_type)
	{
		return in_array($a_type, array('crs', 'grp'));
	}

	/**
	 * Check if a target type supports minimum subscriptions
	 * @param string $a_type
	 * @return bool
	 */
	public function hasMaxSubscriptions($a_type)
	{
		return in_array($a_type, array('crs', 'grp', 'sess'));
	}

	/**
	 * Check if a target type supports membership limitation groupings
	 * @param string $a_type
	 * @return bool
	 */
	public static function hasMemLimitGrouping($a_type)
	{
		return in_array($a_type, array('crs', 'grp'));
	}


	/**
	 * Get the form properties for setting the targets config
	 * @param string $a_type	target object type or 'auto' for auto assignment configuration
	 * @param ilCoSubTargetsConfig $a_config
	 * @return array
	 */
	public function getFormProperties($a_type, $a_config)
	{
		$properties = array();

		$set_type = new ilCheckboxInputGUI($this->plugin->txt('set_sub_type'), 'set_sub_type');
		$set_type->setInfo($this->plugin->txt($a_type == 'auto' ? 'set_sub_type_info_auto' : 'set_sub_type_info'));
		$set_type->setChecked($a_config->set_sub_type);
		$properties[] = $set_type;

		$sub_type = new ilRadioGroupInputGUI($this->plugin->txt('sub_type'), 'sub_type');
		$opt = new ilRadioOption($this->plugin->txt('sub_type_combi'), ilCoSubTargetsConfig::SUB_TYPE_COMBI);
		$sub_type->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_type_direct'), ilCoSubTargetsConfig::SUB_TYPE_DIRECT);
		$sub_type->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_type_confirm'), ilCoSubTargetsConfig::SUB_TYPE_CONFIRM);
		$sub_type->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_type_none'), ilCoSubTargetsConfig::SUB_TYPE_NONE);
		$sub_type->addOption($opt);
		$sub_type->setValue($a_config->sub_type);
		$set_type->addSubItem($sub_type);

		if ($this->hasSubscriptionPeriod($a_type))
		{
			$set_sub_period = new ilCheckboxInputGUI($this->plugin->txt('set_sub_period'), 'set_sub_period');
			$set_sub_period->setInfo($this->plugin->txt($a_type == 'auto' ? 'set_sub_period_info_auto' : 'set_sub_period_info'));
			$set_sub_period->setChecked($a_config->set_sub_period);
			$properties[] = $set_sub_period;

			include_once "Services/Form/classes/class.ilDateDurationInputGUI.php";
			$sub_period = new ilDateDurationInputGUI($this->plugin->txt('sub_period'), "sub_period");
			$sub_period->setShowTime(true);
			$sub_period->setStart(new ilDateTime($a_config->sub_period_start, IL_CAL_UNIX));
			$sub_period->setStartText($this->plugin->txt('sub_period_start'));
			$sub_period->setEnd(new ilDateTime($a_config->sub_period_end,IL_CAL_UNIX));
			$sub_period->setEndText($this->plugin->txt('sub_period_end'));
			$set_sub_period->addSubItem($sub_period);
		}

		if ($this->object->getMethodObject()->hasMinSubscription() && $this->hasMinSubscriptions($a_type))
		{
			$set_min = new ilCheckboxInputGUI($this->plugin->txt('set_sub_min'), 'set_sub_min');
			$set_min->setInfo($this->plugin->txt('set_sub_min_info'));
			$set_min->setChecked($a_config->set_sub_min);
			$properties[] = $set_min;
		}

		if ($this->object->getMethodObject()->hasMaxSubscription() && $this->hasMaxSubscriptions($a_type)) {
			$set_max = new ilCheckboxInputGUI($this->plugin->txt('set_sub_max'), 'set_sub_max');
			$set_max->setInfo($this->plugin->txt('set_sub_max_info'));
			$set_max->setChecked($a_config->set_sub_max);
			$properties[] = $set_max;
		}

		$set_wait = new ilCheckboxInputGUI($this->plugin->txt('set_sub_wait'), 'set_sub_wait');
		$set_wait->setInfo($this->plugin->txt($a_type == 'auto' ? 'set_sub_wait_info_auto' : 'set_sub_wait_info'));
		$set_wait->setChecked($a_config->set_sub_wait);
		$properties[] = $set_wait;

		$sub_wait = new ilRadioGroupInputGUI($this->plugin->txt('sub_wait'), 'sub_wait');
		$sub_wait->setValue($a_config->sub_wait);
		$opt = new ilRadioOption($this->plugin->txt('sub_wait_auto'), ilCoSubTargetsConfig::SUB_WAIT_AUTO);
		$sub_wait->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_wait_manu'), ilCoSubTargetsConfig::SUB_WAIT_MANU);
		$sub_wait->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_wait_none'), ilCoSubTargetsConfig::SUB_WAIT_NONE);
		$sub_wait->addOption($opt);
		$set_wait->addSubItem($sub_wait);

		return $properties;
	}

	/**
	 * Get the inputs from the properties form
	 * @param ilPropertyFormGUI 	$form
	 * @param string 				$a_type target type
	 * @return ilCoSubTargetsConfig
	 */
	public function getFormInputs($form, $a_type)
	{
		$config = new ilCoSubTargetsConfig($this->object);

		$config->set_sub_type = (bool) $form->getInput('set_sub_type');
		$config->sub_type = (string) $form->getInput('sub_type');


		if ($this->hasSubscriptionPeriod($a_type))
		{
			$config->set_sub_period = (bool) $form->getInput('set_sub_period');

			/** @var ilDateDurationInputGUI $sub_period */
			$sub_period = $form->getItemByPostVar('sub_period');
			$config->sub_period_start = (int) $sub_period->getStart()->get(IL_CAL_UNIX);
			$config->sub_period_end = (int) $sub_period->getEnd()->get(IL_CAL_UNIX);
		}
		else
		{
			$config->set_sub_period = false;
			$config->sub_period_start = null;
			$config->sub_period_end = null;
		}

		if ($this->object->getMethodObject()->hasMinSubscription() && $this->hasMinSubscriptions($a_type))
		{
			$config->set_sub_min = (bool) $form->getInput('set_sub_min');
		}
		else
		{
			$config->set_sub_min = false;
		}
		if ($this->object->getMethodObject()->hasMaxSubscription() && $this->hasMaxSubscriptions($a_type))
		{
			$config->set_sub_max = (bool) $form->getInput('set_sub_max');
		}
		else
		{
			$config->set_sub_max = false;
		}

		$config->set_sub_wait = (bool) $form->getInput('set_sub_wait');
		$config->sub_wait = (string) $form->getInput('sub_wait');

		return $config;
	}

	/**
	 * Get an item for a target reference
	 * @param $a_ref_id
	 * @param ilCoSubItem $item	(an existing item that should be modified)
	 * @return ilCoSubItem
	 */
	public function getItemForTarget($a_ref_id, $item = null)
	{
		$this->plugin->includeClass('models/class.ilCoSubItem.php');

		if (!isset($item))
		{
			$item = new ilCoSubItem;
			$item->obj_id = $this->object->getId();
		}
		$item->target_ref_id = $a_ref_id;

		switch (ilObject::_lookupType($a_ref_id, true))
		{
			case 'crs':
				require_once('Modules/Course/classes/class.ilObjCourse.php');
				$course = new ilObjCourse($a_ref_id, true);
				$item->title = $course->getTitle();
				$item->description = $course->getDescription();
				if ($course->isSubscriptionMembershipLimited())
				{
					$item->sub_min = $course->getSubscriptionMinMembers();
					$item->sub_max = $course->getSubscriptionMaxMembers();
				}
				break;

			case 'grp':
				require_once('Modules/Group/classes/class.ilObjGroup.php');
				$group = new ilObjGroup($a_ref_id, true);
				$item->title = $group->getTitle();
				$item->description = $group->getDescription();
				if($group->isMembershipLimited())
				{
					$item->sub_min = $group->getMinMembers();
					$item->sub_max = $group->getMaxMembers();
				}
				break;

			case 'sess':
				require_once('Modules/Session/classes/class.ilObjSession.php');
				$session = new ilObjSession($a_ref_id, true);
				$item->title = $session->getTitle();
				$item->description = $session->getDescription();
				if ($session->isRegistrationUserLimitEnabled())
				{
					$item->sub_min = $session->getRegistrationMinUsers();
					$item->sub_max = $session->getRegistrationMaxUsers();
				}
				break;
		}
		return $item;
	}

	/**
	 * Get a list of unsaved schedules for a target object
	 *
	 * @param $a_ref_id
	 * @return ilCoSubSchedule[]
	 */
	public function getSchedulesForTarget($a_ref_id)
	{
		$this->plugin->includeClass('models/class.ilCoSubSchedule.php');

		$schedules = array();
		switch (ilObject::_lookupType($a_ref_id, true))
		{
			case 'crs':
				if ($this->plugin->withUnivisImport())
				{
					require_once ('Services/UnivIS/classes/class.ilUnivisImport.php');
					require_once ('Services/UnivIS/classes/class.ilUnivisLecture.php');
					$import = new ilUnivisImport();
					$obj_id = ilObject::_lookupObjId($a_ref_id);
					$import_id = ilObject::_lookupImportId($obj_id);
					if (ilUnivisLecture::_isIliasImportId($import_id))
					{
						$import->cleanupLectures();
						if ($import->importLecture($import_id))
						{
							foreach (ilUnivisLecture::_getLecturesData() as $lecture_id => $data)
							{
								$terms = ilUnivisTerm::_getTermsOfLecture($data['key'], $data['semester']);
								$schedules = ilCoSubSchedule::_getFromUnivisTerms($terms);
								break;
							}
						}
					}
				}
				break;

			case 'sess':
				require_once('Modules/Session/classes/class.ilObjSession.php');
				$session = new ilObjSession($a_ref_id, true);
				if ($session->getAppointments())
				{
					/** @var ilSessionAppointment $app */
					$app = $session->getFirstAppointment();
					$schedule = new ilCoSubSchedule();
					$schedule->period_start = $app->getStart()->get(IL_CAL_UNIX);
					$schedule->period_end = $app->getEnd()->get(IL_CAL_UNIX);
					$schedules[] = $schedule;
				}
				break;
		}

		return $schedules;
	}

	/**
	 * Add the assigned users as members to the target objects
	 */
	public function addAssignedUsersAsMembers()
	{
		global $tree;

		include_once('./Modules/Course/classes/class.ilCourseParticipants.php');
		include_once('./Modules/Course/classes/class.ilCourseMembershipMailNotification.php');
		include_once('./Modules/Course/classes/class.ilObjCourseGrouping.php');

		include_once('./Modules/Group/classes/class.ilGroupParticipants.php');
		include_once('./Modules/Group/classes/class.ilGroupMembershipMailNotification.php');

		include_once('./Modules/Session/classes/class.ilSessionParticipants.php');
		include_once('./Modules/Session/classes/class.ilSessionMembershipMailNotification.php');

		// collect the assigning actions to be done
		$actions = array();
		foreach ($this->items as $item)
		{
			if (!empty($item->target_ref_id))
			{
				// get the users to be assigned
				$users = array_keys($this->object->getAssignmentsOfItem($item->item_id));

				// prepare the actions for an object and its parents
				foreach($tree->getNodePath($item->target_ref_id) as $node)
				{
					$ref_id = $node['child'];
					$obj_id = $node['obj_id'];
					$type = $node['type'];

					// index actions by ref_id to treat each object only once
					// parent objects are added first
					if (isset($actions[$ref_id]))
					{
						$actions[$ref_id]['users'] = array_unique(array_merge($actions[$ref_id]['users'], $users));
					}
					else
					{
						$actions[$node['child']] = array(
							'ref_id' => $ref_id,
							'obj_id' => $obj_id,
							'type' => $type,
							'users' => $users
						);
					}
				}
			}
		}

		// do the actions
		foreach ($actions as $ref_id => $action)
		{
			// get membership limitation conditions
			$conditions = self::_getGroupingConditions($action['obj_id'], $action['type']);

			switch($action['type'])
			{
				case 'crs':
					$part_obj = ilCourseParticipants::_getInstanceByObjId($action['obj_id']);
					$role = IL_CRS_MEMBER;
					$mail_obj = new ilCourseMembershipMailNotification();
					$mail_obj->setRefId($ref_id);
					$mail_obj->setType(ilCourseMembershipMailNotification::TYPE_ADMISSION_MEMBER);
					$mail_obj->setLangModules(array('crs','grp','sess'));
					break;

				case 'grp':
					$part_obj = ilGroupParticipants::_getInstanceByObjId($action['obj_id']);
					$role = IL_GRP_MEMBER;
					$mail_obj = new ilGroupMembershipMailNotification();
					$mail_obj->setRefId($ref_id);
					$mail_obj->setType(ilGroupMembershipMailNotification::TYPE_ADMISSION_MEMBER);
					$mail_obj->setLangModules(array('crs','grp','sess'));
					break;

				case 'sess':
					$part_obj = ilSessionParticipants::_getInstanceByObjId($action['obj_id']);
					$role = null;
					$mail_obj = new ilSessionMembershipMailNotification();
					$mail_obj->setRefId($ref_id);
					$mail_obj->setType(ilSessionMembershipMailNotification::TYPE_ADMISSION_MEMBER);
					$mail_obj->setLangModules(array('crs','grp','sess'));
					break;

				default:
					continue 2;	// next action
			}

			$added_members = array();
			foreach ($action['users'] as $user_id)
			{
				// check if user is already a member (relevant for parent course)
				if ($part_obj->isMember($user_id))
				{
					continue;
				}
				// check if user is already member in one of the other groups/course
				if (self::_findGroupingMembership($user_id, $action['type'], $conditions))
				{
					continue;
				}

				// adding the user also deletes the user from the subscribers and from the waiting list
				if (isset($role))
				{
					$part_obj->add($user_id,$role);
				}
				else
				{
					$part_obj->add($user_id);
				}
				$added_members[] = $user_id;
			}

			if (!empty($added_members))
			{
				$mail_obj->setRecipients($added_members);
				$mail_obj->send();
			}
		}
	}

	public function addNonAssignedUsersAsSubscribers()
	{
		include_once('./Modules/Course/classes/class.ilObjCourse.php');
		include_once('./Modules/Course/classes/class.ilCourseWaitingList.php');
		require_once('./Modules/Course/classes/class.ilObjCourseGrouping.php');

		include_once('./Modules/Group/classes/class.ilObjGroup.php');
		include_once('./Modules/Group/classes/class.ilGroupWaitingList.php');

		include_once('./Modules/Session/classes/class.ilObjSession.php');
		include_once('./Modules/Session/classes/class.ilSessionWaitingList.php');


		// collect the actions to be done
		$actions = array();
		foreach ($this->items as $item)
		{
			if (!empty($item->target_ref_id))
			{
				// find users who selected the item
				$users = array();
				foreach ($this->object->getPrioritiesOfItem($item->item_id) as $user_id => $priority)
				{
					// take those that failed to get any assignment
					if (!count($this->object->getAssignmentsOfUser($user_id)))
					{
						$users[] = $user_id;
					}
				}

				$actions[] = array(
					'ref_id' => $item->target_ref_id,
					'obj_id' => ilObject::_lookupObjId($item->target_ref_id),
					'type' => ilObject::_lookupType($item->target_ref_id, true),
					'users' => $users
				);
			}
		}

		// do the actions
		foreach ($actions as $action)
		{
			// get membership limitation conditions
			$conditions = self::_getGroupingConditions($action['obj_id'], $action['type']);

			switch($action['type'])
			{
				case 'grp':
					$object = new ilObjGroup($action['ref_id'], true);
					$list_obj = $object->isWaitingListEnabled() ? new ilGroupWaitingList($action['obj_id']) : null;
					break;

				case 'crs':
					$object = new ilObjCourse($action['ref_id'], true);
					$list_obj = $object->enabledWaitingList() ? new ilCourseWaitingList($action['obj_id']) : null;
					break;

				case 'sess':
					$object = new ilObjSession($action['ref_id'], true);
					$list_obj = $object->isRegistrationWaitingListEnabled() ? new ilSessionWaitingList($action['obj_id']) : null;
					break;
			}

			foreach ($action['users'] as $user_id)
			{
				// check if user is already member in one of the other groups/course
				if (self::_findGroupingMembership($user_id, $action['type'], $conditions))
				{
					continue;
				}

				if (isset($list_obj))
				{
					$list_obj->addToList($user_id);
				}
			}
		}
	}

	/**
	 * Read the list of groupings for the item targets
	 */
	public function getGroupingData()
	{
		require_once('Modules/Course/classes/class.ilObjCourseGrouping.php');
		if (!isset($this->groupings))
		{
			$this->groupings = array();
			foreach ($this->object->getItems() as $item)
			{
				if (isset($item->target_ref_id))
				{
					$obj_id = ilObject::_lookupObjId($item->target_ref_id);
					foreach(ilObjCourseGrouping::_getGroupings($obj_id) as $grouping_id)
					{
						$grouping = new ilObjCourseGrouping($grouping_id);
						$conditions = $grouping->getAssignedItems();
						$this->groupings[] = array('grouping' => $grouping, 'conditions' => $conditions);
					}
				}
			}
		}
		return $this->groupings;
	}

	/**
	 * Get the groupings of an item
	 * @param ilCoSubItem $a_item
	 * @return ilObjCourseGrouping[]
	 */
	public function getGroupingsOfItem($a_item)
	{
		if (!isset($a_item->target_ref_id))
		{
			return array();
		}
		$groupings = array();
		foreach ($this->getGroupingData() as $groupingData)
		{
			foreach ($groupingData['conditions'] as $condition)
			{
				if ($condition['target_ref_id'] == $a_item->target_ref_id)
				{
					$groupings[] = $groupingData['grouping'];
				}
			}
		}
		return $groupings;
	}

	/**
	 * Add a grouping for the items
	 */
	public function addGrouping()
	{
		require_once('Modules/Course/classes/class.ilObjCourseGrouping.php');
		$grouping = new ilObjCourseGrouping();
		$ref_ids = $this->getTargetRefIds();
		if (empty($ref_ids))
		{
			return;
		}

		$ref_id = $ref_ids[0];
		$obj_id = ilObject::_lookupObjId($ref_id);

		$grouping->setContainerRefId($ref_id);
		$grouping->setContainerObjId($obj_id);
		$grouping->setContainerType($this->getCommonType());
		$grouping->setTitle($this->object->getTitle());
		$grouping->setUniqueField('login');
		$grouping->create($ref_id, $obj_id);

		foreach($ref_ids as $ref_id)
		{
			$obj_id = ilObject::_lookupObjId($ref_id);
			$grouping->assign($ref_id, $obj_id);
		}
	}

	/**
	 * Remove a course grouping from the items
	 */
	public function removeGrouping()
	{
		foreach ($this->items as $item)
		{
			foreach ($this->getGroupingsOfItem($item) as $grouping)
			{
				$grouping->deassign($item->target_ref_id, ilObject::_lookupObjId($item->target_ref_id));
			}
		}

		foreach($this->getGroupingData() as $data)
		{
			/** @var ilObjCourseGrouping $grouping */
			$grouping = $data['grouping'];
			if ($grouping->getCountAssignedItems() < 2)
			{
				$grouping->delete();
			}
		}
	}

	/**
	 * Get grouping conditions of a container object
	 *
	 * @param 	int     $a_obj_id
	 * @param	string	$a_type
	 * @return 	array   assoc: grouping conditions
	 */
	static function _getGroupingConditions($a_obj_id, $a_type)
	{
		global $tree;

		if (!self::hasMemLimitGrouping($a_type))
		{
			return array();
		}

		static $cached_conditions;
		if (isset($cached_conditions[$a_obj_id]))
		{
			return $cached_conditions[$a_obj_id];
		}

		include_once './Services/AccessControl/classes/class.ilConditionHandler.php';

		$ref_id = current(ilObject::_getAllReferences($a_obj_id));
		$trigger_ids = array();
		$conditions = array();

		foreach(ilConditionHandler::_getConditionsOfTarget($ref_id, $a_obj_id, $a_type) as $condition)
		{
			if($condition['operator'] == 'not_member')
			{
				$trigger_ids[] = $condition['trigger_obj_id'];
			}
		}
		foreach ($trigger_ids as $trigger_id)
		{
			foreach(ilConditionHandler::_getConditionsOfTrigger('crsg', $trigger_id) as $condition)
			{
				// Handle deleted items
				if(!$tree->isDeleted($condition['target_ref_id'])
					and $condition['operator'] == 'not_member')
				{
					$conditions[$condition['target_ref_id']] = $condition;
				}
			}
		}

		$cached_conditions[$a_obj_id] = array_values($conditions);
		return $cached_conditions[$a_obj_id];
	}


	/**
	 * Check the grouping conditions for a user
	 *
	 * @param  	int 	    $user_id
	 * @param    string     $type 'grp' or 'crs'
	 * @param  	array 		$conditions
	 * @return   string     obj_id
	 */
	static function _findGroupingMembership($user_id, $type, $conditions)
	{
		foreach ($conditions as $condition)
		{
			if ($type == 'crs')
			{
				include_once('Modules/Course/classes/class.ilCourseParticipants.php');
				$members = ilCourseParticipants::_getInstanceByObjId($condition['target_obj_id']);
				if($members->isGroupingMember($user_id, $condition['value']))
				{
					return $condition['target_obj_id'];
				}
			}
			elseif ($type == 'grp')
			{
				include_once('Modules/Group/classes/class.ilGroupParticipants.php');
				$members = ilGroupParticipants::_getInstanceByObjId($condition['target_obj_id']);
				if($members->isGroupingMember($user_id, $condition['value']))
				{
					return $condition['target_obj_id'];
				}
			}
		}
		return false;
	}

	/**
	 * Check if items with targets exist
	 */
	public function targetsExist()
	{
		$ref_ids = $this->getTargetRefIds();
		return !empty($ref_ids);
	}

	/**
	 * Check if all existing targets are writable
	 */
	public function targetsWritable()
	{
		/** @var ilAccessHandler  $ilAccess*/
		global $ilAccess;

		foreach ($this->getTargetRefIds() as $ref_id)
		{
			if (!$ilAccess->checkAccess('write', '', $ref_id))
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * get the common type of the targets
	 * @return string|null		type or null if they have different types
	 */
	public function getCommonType()
	{
		$type = null;
		foreach ($this->getTargetRefIds() as $ref_id)
		{
			$newtype = ilObject::_lookupType($ref_id, true);
			if (empty($type))
			{
				$type = $newtype;
			}
			elseif ($type != $newtype)
			{
				return null;
			}
		}
		return $type;
	}

	/**
	 * Get the ref_ids of all targets
	 * @return int[]
	 */
	public function getTargetRefIds()
	{
		$ref_ids = array();
		foreach($this->items as $item)
		{
			if (!empty($item->target_ref_id))
			{
				$ref_ids[] = $item->target_ref_id;
			}
		}
		return $ref_ids;
	}

	/**
	 * Set the items by an array of item_ids
	 * @var int[]
	 */
	public function setItemsByIds($a_item_ids)
	{
		$this->items = array();
		foreach ($this->object->getItems() as $item)
		{
			if (in_array($item->item_id, $a_item_ids))
			{
				$this->items[$item->item_id] = $item;
			}
		}
	}

	/**
	 * Set the items to be treated
	 * @var ilCoSubItem[]
	 */
	public function setItems($a_items)
	{
		$this->items = array();
		foreach ($a_items as $item)
		{
			$this->items[$item->item_id] = $item;
		}
	}

	/**
	 * Restrict the list of items to those with writable targets
	 */
	public function filterWritableTargets()
	{
		/** @var ilAccessHandler $ilAccess */
		global $ilAccess;

		foreach($this->items as $item_id => $item)
		{
			if (!$ilAccess->checkAccess('write','', $item->target_ref_id))
			{
				unset($this->items[$item_id]);
			}
		}
	}

	/**
	 * Restrict the list of items to existing, untrashed targets
	 */
	public function filterUntrashedTargets()
	{
		foreach($this->items as $item_id => $item)
		{
			if (!ilObject::_exists($item->target_ref_id, true) || ilObject::_isInTrash($item->target_ref_id))
			{
				unset($this->items[$item_id]);
			}
		}
	}

	/**
	 * Apply the default configuration settings to the target objects
	 * This is done when new target objects are connected
	 * - the subscription type is set to the combined subscription
	 * - the subscription period is set to the period of the combined subscription
	 * @param $items
	 * @return bool
	 */
	public function applyDefaultTargetsConfig()
	{
		$config = new ilCoSubTargetsConfig($this->object);
		$config->set_sub_type = true;
		$config->sub_type = ilCoSubTargetsConfig::SUB_TYPE_COMBI;

		$config->set_sub_period = true;
		$config->sub_period_start = $this->object->getSubscriptionStart()->get(IL_CAL_UNIX);
		$config->sub_period_end = $this->object->getSubscriptionEnd()->get(IL_CAL_UNIX);

		try
		{
			$this->applyTargetsConfig($config);
		}
		catch (Exception $e)
		{
			return false;
		}

		return true;
	}


	/**
	 * Apply configuration settings to the target objects
	 * @param ilCoSubTargetsConfig $config
	 * @throws Exception
	 */
	public function applyTargetsConfig($config)
	{
		require_once('Services/Object/classes/class.ilObjectFactory.php');
		require_once('Services/Membership/classes/class.ilMembershipRegistrationSettings.php');

		$targets = array();
		foreach($this->items as $item)
		{
			if (!empty($item->target_ref_id))
			{
				$target = ilObjectFactory::getInstanceByRefId($item->target_ref_id, false);
				if (!is_object($target))
				{
					throw new Exception(sprintf($this->plugin->txt('target_object_not_found'), $item->title));
				}
				if (!in_array($target->getType(), $this->plugin->getAvailableTargetTypes()))
				{
					throw new Exception(sprintf($this->plugin->txt('target_object_wrong_type'), $item->title));
				}

				$targets[$item->item_id] = $target;
			}
		}

		foreach ($targets as $item_id => $target)
		{
			$item = $this->items[$item_id];
			switch ($target->getType())
			{
				case 'crs':
					/** @var ilObjCourse $target */

					if ($config->set_sub_type)
					{
						switch ($config->sub_type)
						{
							case ilCoSubTargetsConfig::SUB_TYPE_COMBI:
								$target->setSubscriptionType(IL_CRS_SUBSCRIPTION_OBJECT);
								$target->setSubscriptionLimitationType(IL_CRS_SUBSCRIPTION_LIMITED);
								$target->setSubscriptionRefId($this->object->getRefId());
								$target->setSubscriptionStart($this->object->getSubscriptionStart()->get(IL_CAL_UNIX));
								$target->setSubscriptionEnd($this->object->getSubscriptionEnd()->get(IL_CAL_UNIX));

								break;
							case ilCoSubTargetsConfig::SUB_TYPE_CONFIRM:
								$target->setSubscriptionType(IL_CRS_SUBSCRIPTION_CONFIRMATION);
								break;
							case ilCoSubTargetsConfig::SUB_TYPE_DIRECT:
								$target->setSubscriptionType(IL_CRS_SUBSCRIPTION_DIRECT);
								break;
							case ilCoSubTargetsConfig::SUB_TYPE_NONE:
								$target->setSubscriptionType(IL_CRS_SUBSCRIPTION_DEACTIVATED);
								break;
						}
					}

					if ($config->set_sub_period)
					{
						$target->setSubscriptionLimitationType(IL_CRS_SUBSCRIPTION_LIMITED);
						$target->setSubscriptionStart($config->sub_period_start);
						$target->setSubscriptionEnd($config->sub_period_end);
					}

					if ($config->set_sub_min)
					{
						$target->enableSubscriptionMembershipLimitation(true);
						$target->setSubscriptionMinMembers($item->sub_min);
					}
					if ($config->set_sub_max)
					{
						$target->enableSubscriptionMembershipLimitation(true);
						$target->setSubscriptionMaxMembers($item->sub_max);
					}

					if ($config->set_sub_wait)
					{
						switch($config->sub_wait)
						{
							case ilCoSubTargetsConfig::SUB_WAIT_AUTO:
								$target->enableWaitingList(true);
								$target->setWaitingListAutoFill(true);
								break;
							case ilCoSubTargetsConfig::SUB_WAIT_MANU:
								$target->enableWaitingList(true);
								$target->setWaitingListAutoFill(false);
								break;
							case ilCoSubTargetsConfig::SUB_WAIT_NONE:
								$target->enableWaitingList(false);
								break;
						}
					}

					$target->update();
					break;

				case 'grp':
					/** @var ilObjGroup $target */

					if ($config->set_sub_type)
					{
						switch ($config->sub_type)
						{
							case ilCoSubTargetsConfig::SUB_TYPE_COMBI:
								$target->setRegistrationType(GRP_REGISTRATION_OBJECT);
								$target->setRegistrationRefId($this->object->getRefId());
								$target->setRegistrationStart($this->object->getSubscriptionStart());
								$target->setRegistrationEnd($this->object->getSubscriptionEnd());

								break;
							case ilCoSubTargetsConfig::SUB_TYPE_CONFIRM:
								$target->setRegistrationType(GRP_REGISTRATION_REQUEST);
								break;
							case ilCoSubTargetsConfig::SUB_TYPE_DIRECT:
								$target->setRegistrationType(GRP_REGISTRATION_DIRECT);
								break;
							case ilCoSubTargetsConfig::SUB_TYPE_NONE:
								$target->setRegistrationType(GRP_REGISTRATION_DEACTIVATED);
								break;
						}
					}

					if ($config->set_sub_period)
					{
						$target->enableUnlimitedRegistration(false);
						$target->setRegistrationStart(new ilDateTime($config->sub_period_start, IL_CAL_UNIX));
						$target->setRegistrationEnd(new ilDateTime($config->sub_period_end, IL_CAL_UNIX));
					}

					if ($config->set_sub_min)
					{
						$target->enableMembershipLimitation(true);
						$target->setMinMembers($item->sub_min);
					}
					if ($config->set_sub_max)
					{
						$target->enableMembershipLimitation(true);
						$target->setMaxMembers($item->sub_max);
					}

					if ($config->set_sub_wait)
					{
						switch($config->sub_wait)
						{
							case ilCoSubTargetsConfig::SUB_WAIT_AUTO:
								$target->enableWaitingList(true);
								$target->setWaitingListAutoFill(true);
								break;
							case ilCoSubTargetsConfig::SUB_WAIT_MANU:
								$target->enableWaitingList(true);
								$target->setWaitingListAutoFill(false);
								break;
							case ilCoSubTargetsConfig::SUB_WAIT_NONE:
								$target->enableWaitingList(false);
								break;
						}
					}

					$target->update();
					break;

				case "sess";
					/** @var ilObjSession $target */

					if ($config->set_sub_type)
					{
						switch ($config->sub_type)
						{
							case ilCoSubTargetsConfig::SUB_TYPE_COMBI:
								$target->setRegistrationType(ilMembershipRegistrationSettings::TYPE_OBJECT);
								$target->setRegistrationRefId($this->object->getRefId());
								break;
							case ilCoSubTargetsConfig::SUB_TYPE_CONFIRM:
								$target->setRegistrationType(ilMembershipRegistrationSettings::TYPE_REQUEST);
								break;
							case ilCoSubTargetsConfig::SUB_TYPE_DIRECT:
								$target->setRegistrationType(ilMembershipRegistrationSettings::TYPE_DIRECT);
								break;
							case ilCoSubTargetsConfig::SUB_TYPE_NONE:
								$target->setRegistrationType(ilMembershipRegistrationSettings::TYPE_NONE);
								break;
						}
					}

					if ($config->set_sub_max)
					{
						$target->enableRegistrationUserLimit(true);
						$target->setRegistrationMaxUsers($item->sub_max);
					}

					if ($config->set_sub_wait)
					{
						switch($config->sub_wait)
						{
							case ilCoSubTargetsConfig::SUB_WAIT_AUTO:
								$target->enableRegistrationWaitingList(true);
								$target->setWaitingListAutoFill(true);
								break;
							case ilCoSubTargetsConfig::SUB_WAIT_MANU:
								$target->enableRegistrationWaitingList(true);
								$target->setWaitingListAutoFill(false);
								break;
							case ilCoSubTargetsConfig::SUB_WAIT_NONE:
								$target->enableRegistrationWaitingList(false);
								$target->setWaitingListAutoFill(false);
								break;
						}
					}

					$target->update();
					break;
			}
		}
	}
}