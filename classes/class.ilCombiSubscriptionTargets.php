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

	/**
	 * Constructor
	 * @param ilObjCombiSubscription        $a_object
	 * @param ilCombiSubscriptionPlugin     $a_plugin
	 */
	public function __construct($a_object, $a_plugin)
	{
		$this->object = $a_object;
		$this->plugin = $a_plugin;
	}

	/**
	 * Add the assigned users as members to the target objects
	 */
	public function addAssignedUsersAsMembers()
	{
		include_once('./Modules/Group/classes/class.ilGroupParticipants.php');
		include_once('./Modules/Group/classes/class.ilGroupMembershipMailNotification.php');

		include_once('./Modules/Course/classes/class.ilCourseParticipants.php');
		include_once('./Modules/Course/classes/class.ilCourseMembershipMailNotification.php');
		require_once('./Modules/Course/classes/class.ilObjCourseGrouping.php');

		// collect the actions to be done
		$actions = array();
		foreach ($this->object->getItems() as $item)
		{
			if (!empty($item->target_ref_id))
			{
				$actions[] = array(
					'ref_id' => $item->target_ref_id,
					'obj_id' => ilObject::_lookupObjId($item->target_ref_id),
					'type' => ilObject::_lookupType($item->target_ref_id, true),
					'users' => array_keys($this->object->getAssignmentsOfItem($item->item_id))
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
					$part_obj = ilGroupParticipants::_getInstanceByObjId($action['obj_id']);
					$role = IL_GRP_MEMBER;
					$notification_type = ilGroupMembershipMailNotification::TYPE_ADMISSION_MEMBER;
					break;

				case 'crs':
				default:
					$part_obj = ilCourseParticipants::_getInstanceByObjId($action['obj_id']);
					$role = IL_CRS_MEMBER;
					$notification_type = $part_obj->NOTIFY_ACCEPT_SUBSCRIBER;
					break;
			}

			foreach ($action['users'] as $user_id)
			{
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
		foreach ($this->object->getItems() as $item)
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
}