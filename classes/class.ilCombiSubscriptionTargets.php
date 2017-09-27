<?php

/**
 * Target management for combined subscriptions
 * All course/group related functions should go here
 */
class ilCombiSubscriptionTargets
{
	const SUB_TYPE_COMBI = 'combi';
	const SUB_TYPE_DIRECT = 'direct';
	const SUB_TYPE_CONFIRM = 'confirm';
	const SUB_TYPE_NONE = 'none';

	const SUB_WAIT_MANU = 'manu';
	const SUB_WAIT_AUTO = 'auto';
	const SUB_WAIT_NONE = 'none';


	/** @var  ilObjCombiSubscription */
	protected $object;

	/** @var ilCombiSubscriptionPlugin  */
	protected $plugin;

	/** @var  ilCoSubItem[] */
	protected $items = array();

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
	}

	/**
	 * Add the assigned users as members to the target objects
	 */
	public function addAssignedUsersAsMembers()
	{
		global $tree;

		include_once('./Modules/Group/classes/class.ilGroupParticipants.php');
		include_once('./Modules/Group/classes/class.ilGroupMembershipMailNotification.php');

		include_once('./Modules/Course/classes/class.ilCourseParticipants.php');
		include_once('./Modules/Course/classes/class.ilCourseMembershipMailNotification.php');
		include_once('./Modules/Course/classes/class.ilObjCourseGrouping.php');

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
				case 'grp':
					$part_obj = ilGroupParticipants::_getInstanceByObjId($action['obj_id']);
					$role = IL_GRP_MEMBER;
					$notification_type = ilGroupMembershipMailNotification::TYPE_ADMISSION_MEMBER;
					break;

				case 'crs':
					$part_obj = ilCourseParticipants::_getInstanceByObjId($action['obj_id']);
					$role = IL_CRS_MEMBER;
					$notification_type = $part_obj->NOTIFY_ACCEPT_SUBSCRIBER;
					break;

				default:
					continue 2;	// next action
			}

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
				$part_obj->add($user_id,$role);
				$part_obj->sendNotification($notification_type, $user_id);
			}
		}
	}

	public function addNonAssignedUsersAsSubscribers()
	{
		include_once('./Modules/Group/classes/class.ilObjGroup.php');
		include_once('./Modules/Group/classes/class.ilGroupWaitingList.php');

		include_once('./Modules/Course/classes/class.ilObjCourse.php');
		include_once('./Modules/Course/classes/class.ilCourseWaitingList.php');
		require_once('./Modules/Course/classes/class.ilObjCourseGrouping.php');


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
					if ($object->isWaitingListEnabled())
					{
						$list_obj = new ilGroupWaitingList($action['obj_id']);
					}
					else
					{
						$list_obj = null;
					}
					break;

				case 'crs':
					$object = new ilObjCourse($action['ref_id'], true);
					if ($object->enabledWaitingList())
					{
						$action['list'] = 'waiting_list';
						$list_obj = new ilCourseWaitingList($action['obj_id']);
					}
					else
					{
						$list_obj = null;
					}
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
	 * Get grouping conditions of a container object
	 *
	 * @param 	int     $a_obj_id
	 * @param	string	$a_type
	 * @return 	array   assoc: grouping conditions
	 */
	function _getGroupingConditions($a_obj_id, $a_type)
	{
		global $tree;

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
	function _findGroupingMembership($user_id, $type, $conditions)
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
				$this->items[] = $item;
			}
		}
	}

	/**
	 * Set the target configurations
	 * @param string $sub_type
	 * @param int $sub_start
	 * @param int $sub_end
	 * @param bool $set_min
	 * @param bool $set_max
	 * @param string $sub_wait
	 * @throws Exception
	 */
	public function setTargetsConfig($sub_type = null, $sub_start = null, $sub_end = null, $set_min = null, $set_max = null, $sub_wait = null)
	{
		/** @var ilAccessHandler  $ilAccess*/
		global $ilAccess;

		require_once('Services/Object/classes/class.ilObjectFactory.php');

		$items = array();
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
				if ($target->getType() != 'crs' && $target->getType() != 'grp')
				{
					throw new Exception(sprintf($this->plugin->txt('target_object_wrong_type'), $item->title));
				}
				if (!$ilAccess->checkAccess('write', '', $item->target_ref_id))
				{
					throw new Exception(sprintf($this->plugin->txt('target_object_not_writable'), $item->title));
				}

				$items[$item->item_id] = $item;
				$targets[$item->item_id] = $target;
			}
		}

		foreach ($targets as $item_id => $target)
		{
			$item = $items[$item_id];
			switch ($target->getType())
			{
				case 'crs':
					/** @var ilObjCourse $target */
					switch ($sub_type)
					{
						case self::SUB_TYPE_COMBI:
							$target->setSubscriptionType(IL_CRS_SUBSCRIPTION_OBJECT);
							$target->setSubscriptionLimitationType(IL_CRS_SUBSCRIPTION_LIMITED);
							$target->setSubscriptionRefId($this->object->getRefId());
							$target->setSubscriptionStart($this->object->getSubscriptionStart()->get(IL_CAL_UNIX));
							$target->setSubscriptionEnd($this->object->getSubscriptionEnd()->get(IL_CAL_UNIX));

							break;
						case self::SUB_TYPE_CONFIRM:
							$target->setSubscriptionType(IL_CRS_SUBSCRIPTION_CONFIRMATION);
							break;
						case self::SUB_TYPE_DIRECT:
							$target->setSubscriptionType(IL_CRS_SUBSCRIPTION_DIRECT);
							break;
						case self::SUB_TYPE_NONE:
							$target->setSubscriptionType(IL_CRS_SUBSCRIPTION_DEACTIVATED);
							break;
					}

					if (isset($sub_start))
					{
						$target->setSubscriptionLimitationType(IL_CRS_SUBSCRIPTION_LIMITED);
						$target->setSubscriptionStart($sub_start);
					}

					if (isset($sub_end))
					{
						$target->setSubscriptionLimitationType(IL_CRS_SUBSCRIPTION_LIMITED);
						$target->setSubscriptionEnd($sub_end);
					}

					if ($set_min)
					{
						$target->enableSubscriptionMembershipLimitation(true);
						$target->setSubscriptionMinMembers($item->sub_min);
					}
					if ($set_max)
					{
						$target->enableSubscriptionMembershipLimitation(true);
						$target->setSubscriptionMaxMembers($item->sub_max);
					}

					switch($sub_wait)
					{
						case self::SUB_WAIT_AUTO:
							$target->enableWaitingList(true);
							$target->setWaitingListAutoFill(true);
							break;
						case self::SUB_WAIT_MANU:
							$target->enableWaitingList(true);
							$target->setWaitingListAutoFill(false);
							break;
						case self::SUB_WAIT_NONE:
							$target->enableWaitingList(false);
							break;
					}

					$target->update();
					break;

				case 'grp':
					/** @var ilObjGroup $target */
					switch ($sub_type)
					{
						case self::SUB_TYPE_COMBI:
							$target->setRegistrationType(GRP_REGISTRATION_OBJECT);
							$target->setRegistrationRefId($this->object->getRefId());
							$target->setRegistrationStart($this->object->getSubscriptionStart());
							$target->setRegistrationEnd($this->object->getSubscriptionEnd());

							break;
						case self::SUB_TYPE_CONFIRM:
							$target->setRegistrationType(GRP_REGISTRATION_REQUEST);
							break;
						case self::SUB_TYPE_DIRECT:
							$target->setRegistrationType(GRP_REGISTRATION_DIRECT);
							break;
						case self::SUB_TYPE_NONE:
							$target->setRegistrationType(GRP_REGISTRATION_DEACTIVATED);
							break;
					}

					if (isset($sub_start))
					{
						$target->enableUnlimitedRegistration(false);
						$target->setRegistrationStart(new ilDateTime($sub_start, IL_CAL_UNIX));
					}

					if (isset($sub_end))
					{
						$target->enableUnlimitedRegistration(false);
						$target->setRegistrationEnd(new ilDateTime($sub_end, IL_CAL_UNIX));
					}

					if ($set_min)
					{
						$target->enableMembershipLimitation(true);
						$target->setMinMembers($item->sub_min);
					}
					if ($set_max)
					{
						$target->enableMembershipLimitation(true);
						$target->setMaxMembers($item->sub_max);
					}

					switch($sub_wait)
					{
						case self::SUB_WAIT_AUTO:
							$target->enableWaitingList(true);
							$target->setWaitingListAutoFill(true);
							break;
						case self::SUB_WAIT_MANU:
							$target->enableWaitingList(true);
							$target->setWaitingListAutoFill(false);
							break;
						case self::SUB_WAIT_NONE:
							$target->enableWaitingList(false);
							break;
					}

					$target->update();
					break;
			}
		}
	}
}