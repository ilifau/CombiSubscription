<?php

include_once('./Services/Repository/classes/class.ilObjectPlugin.php');

/**
* Application class for combined subscription repository object
*
* @author Fred Neumann <fred.neumann@gmx.de>
* @version $Id$
*/
class ilObjCombiSubscription extends ilObjectPlugin
{
	# region class constants
	const SATISFIED_NOT = 0;
	const SATISFIED_MEDIUM = 1;
	const SATISFIED_FULL = 2;
	# endregion

	# region class variables

	protected $online = false;
	protected $explanation = '';
	protected $sub_start = null;
	protected $sub_end = null;
	protected $show_bars = true;
	protected $min_choices = 0;
	protected $method = 'ilCoSubMethodRandom';
	protected $class_properties = array();

	/** @var  ilCombiSubscriptionPlugin */
	public $plugin;

	/** @var  ilCoSubMethodBase | null */
	protected $method_object;

	/** @var ilCoSubItem[] | null */
	protected $items;

	/** @var  ilCoSubRun[] | null  (numerically indexed) */
	protected $runs;

	/** @var  array     user_id => item_id => priority */
	protected $priorities;

	/** @var  bool      the priorities of all users are loaded */
	protected $all_priorities_loaded = false;

	/** @var  array     run_id => user_id => item_id => assign_id (run_id is 0 for the chosen assignments) */
	protected $assignments;

	# endregion


	/**
	 * Constructor
	 *
	 * @access    public
	 * @param int $a_ref_id
	 */
	function __construct($a_ref_id = 0)
	{
		parent::__construct($a_ref_id);
	}
	

	/**
	* Get type
	*/
	final function initType()
	{
		$this->setType('xcos');
	}
	
	/**
	* Create object
	*/
	function doCreate()
	{
		global $ilDB;

		$dummyDate = new ilDateTime(time(), IL_CAL_UNIX);

		$ilDB->manipulate("INSERT INTO rep_robj_xcos_data ".
			"(obj_id, is_online, explanation, sub_start, sub_end, show_bars, min_choices, method) VALUES (".
			$ilDB->quote($this->getId(), 'integer').','.
			$ilDB->quote(0, 'integer').','.
			$ilDB->quote($this->plugin->txt('default_explanation'), 'text').','.
			$ilDB->quote($dummyDate->get(IL_CAL_DATETIME), 'text').','.
			$ilDB->quote($dummyDate->get(IL_CAL_DATETIME), 'text').','.
			$ilDB->quote($this->getShowBars(), 'integer').','.
			$ilDB->quote($this->getMinChoices(), 'integer').','.
			$ilDB->quote('ilCoSubMethodRandom', 'text').
			")");
	}
	
	/**
	* Read data from db
	*/
	function doRead()
	{
		global $ilDB;
		
		$set = $ilDB->query("SELECT * FROM rep_robj_xcos_data ".
			" WHERE obj_id = ".$ilDB->quote($this->getId(), 'integer')
			);
		if ($rec = $ilDB->fetchAssoc($set))
		{
			$this->setOnline($rec['is_online']);
			$this->setExplanation($rec['explanation']);
			$this->setSubscriptionStart(new ilDateTime($rec['sub_start'],IL_CAL_DATETIME));
			$this->setSubscriptionEnd(new ilDateTime($rec['sub_end'],IL_CAL_DATETIME));
			$this->setShowBars((bool) $rec['show_bars']);
			$this->setMinChoices($rec['min_choices']);
			$this->setMethod($rec['method']);
		}
		else
		{
			$this->setOnline(false);
			$this->setExplanation($this->plugin->txt('default_explanation'));
			$this->setSubscriptionStart(new ilDateTime(time(), IL_CAL_UNIX));
			$this->setSubscriptionEnd(new ilDateTime(time(), IL_CAL_UNIX));
			$this->setShowBars(true);
			$this->setMinChoices(0);
			$this->setMethod('ilCoSubMethodRandom');
		}
	}
	
	/**
	* Update data
	*/
	function doUpdate()
	{
		global $ilDB;
		
		$ilDB->manipulate($up = "UPDATE rep_robj_xcos_data SET ".
			" is_online = ".$ilDB->quote($this->getOnline(), 'integer').','.
			" explanation = ".$ilDB->quote($this->getExplanation(), 'text').','.
			" sub_start = ".$ilDB->quote($this->getSubscriptionStart()->get(IL_CAL_DATETIME), 'timestamp').','.
			" sub_end = ".$ilDB->quote($this->getSubscriptionEnd()->get(IL_CAL_DATETIME), 'timestamp').','.
			" show_bars = ".$ilDB->quote($this->getShowBars(), 'integer').','.
			" min_choices = ".$ilDB->quote($this->getMinChoices(), 'integer').','.
			" method = ".$ilDB->quote($this->getMethod(),'text').
			" WHERE obj_id = ".$ilDB->quote($this->getId(), 'integer')
			);
	}
	
	/**
	* Delete data from db
	*/
	function doDelete()
	{
		global $ilDB;
		
		$ilDB->manipulate("DELETE FROM rep_robj_xcos_data WHERE ".
			" obj_id = ".$ilDB->quote($this->getId(), 'integer')
			);
		
	}
	
	/**
	 * Do Cloning
	 * @param self $new_obj
	 * @param integer $a_target_id
	 * @param int	$a_copy_id
	 */
	function doCloneObject($new_obj, $a_target_id, $a_copy_id = null)
	{
		$new_obj->setOnline(false);
		$new_obj->setExplanation($this->getExplanation());
		$new_obj->setSubscriptionStart($this->getSubscriptionStart());
		$new_obj->setSubscriptionEnd($this->getSubscriptionEnd());
		$new_obj->setShowBars($this->getShowBars());
		$new_obj->setMinChoices($this->getMinChoices());
		$new_obj->setMethod($this->getMethod());
		$new_obj->update();

		// clone the properties of methods etc.
		$this->cloneProperties($new_obj->getId());

		// clone the items
		foreach ($this->getItems() as $item)
		{
			$item->saveClone($new_obj->getId());
		}
	}

	/**
	* Set online
	*
	* @param	boolean		$a_val
	*/
	public function setOnline($a_val)
	{
		$this->online = $a_val;
	}
	
	/**
	* Get online
	*
	* @return	boolean		online
	*/
	public function getOnline()
	{
		return $this->online;
	}

	/**
	 * Set explanation
	 *
	 * @param	string		$a_val
	 */
	public function setExplanation($a_val)
	{
		$this->explanation = $a_val;
	}

	/**
	 * Get explanation
	 *
	 * @return	string		explanation
	 */
	public function getExplanation()
	{
		return $this->explanation;
	}


	/**
	 * Set Subscription Start
	 * @param ilDateTime    $a_sub_start
	 */
	public function setSubscriptionStart($a_sub_start)
	{
		$this->sub_start = $a_sub_start;
	}

	/**
	 * Get Subscription Start
	 * @return ilDateTime
	 */
	public function getSubscriptionStart()
	{
		return $this->sub_start;
	}

	/**
	 * Set Subscription End
	 * @param $a_sub_end
	 * @internal param ilDateTime $a_sub_start
	 */
	public function setSubscriptionEnd($a_sub_end)
	{
		$this->sub_end = $a_sub_end;
	}

	/**
	 * Get Subscription End
	 * @return ilDateTime
	 */
	public function getSubscriptionEnd()
	{
		return $this->sub_end;
	}

	/**
	 * Check if the current date is before the subscription period
	 */
	public function isBeforeSubscription()
	{
		if (ilDateTime::_before(new ilDateTime(time(),IL_CAL_UNIX), $this->getSubscriptionStart()))
		{
			return true;
		}
		return false;
	}

	/**
	 * Check if the current date is after the subscription period
	 */
	public function isAfterSubscription()
	{
		if (ilDateTime::_after(new ilDateTime(time(),IL_CAL_UNIX), $this->getSubscriptionEnd()))
		{
			return true;
		}
		return false;
	}

	/**
	 * Get if bars should be shown on the registratin screen
	 */
	public function getShowBars()
	{
		return $this->show_bars;
	}


	/**
	 * Set if bars should be shown on the registration screen
	 * @param bool	$a_show_bars
	 */
	public function setShowBars($a_show_bars)
	{
		$this->show_bars = (bool) $a_show_bars;
	}


	/**
	 * Get the minimum choices that must be selected for registration
	 * @return int
	 */
	public function getMinChoices()
	{
		return (int) $this->min_choices;
	}


	/**
	 * Set the minimum choices that must be selected for registration
	 * @param	int		$a_choices
	 */
	public function setMinChoices($a_choices)
	{
		$this->min_choices = (int) $a_choices;
	}


	/**
	 * Check if the current user has access to extended user data
	 * @return bool
	 */
	public function hasExtendedUserData()
	{
		global $rbacsystem;
		include_once('Services/PrivacySecurity/classes/class.ilPrivacySettings.php');
		$privacy = ilPrivacySettings::_getInstance();
		return $rbacsystem->checkAccess('export_member_data',$privacy->getPrivacySettingsRefId());
	}


	/**
	 * Check if the platform has studydata available (StudOn only)
	 */
	public function hasStudyData()
	{
		return file_exists('Services/StudyData/classes/class.ilStudyData.php');
	}

	/**
	 * Get a property for this class
	 * @param   string  $a_key
	 * @param   string  $a_default_value
	 * @return string	value
	 */
	public function getProperty($a_key, $a_default_value)
	{
		return $this->getClassProperty(get_class($this), $a_key, $a_default_value);
	}

	/**
	 * Set a property for this class
	 * @param   string  $a_key
	 * @param   string  $a_value
	 */
	public function setProperty($a_key, $a_value)
	{
		$this->setClassProperty(get_class($this),  $a_key, $a_value);
	}

	/**
	 * Get a property for a certain class
	 * @param   string  $a_class
	 * @param   string  $a_key
	 * @param   string  $a_default_value
	 * @return string	value
	 */
	public function getClassProperty($a_class, $a_key, $a_default_value)
	{
		$this->readClassProperties($a_class);
		return isset($this->class_properties[$a_class][$a_key]) ? $this->class_properties[$a_class][$a_key] : $a_default_value;
	}

	/**
	 * Set an object property for a certain class
	 * @param   string  $a_class
	 * @param   string  $a_key
	 * @param   string  $a_value
	 */
	public function setClassProperty($a_class, $a_key, $a_value)
	{
		/** @var ilDB $ilDB */
		global $ilDB;

		$ilDB->replace('rep_robj_xcos_prop',
			array(
				'obj_id' => array('integer', $this->getId()),
				'class' => array('text', $a_class),
				'property' => array('text', $a_key),
			),
			array(
				'value' => array('text', (string) $a_value)
			)
		);

		$this->readClassProperties($a_class);
		$this->class_properties[$a_class][$a_key] = $a_value;
	}

	/**
	 * Read the object properties for a certain class
	 * @param $a_class
	 */
	private function readClassProperties($a_class)
	{
		if (!isset($this->class_properties[$a_class]))
		{
			/** @var ilDB $ilDB */
			global $ilDB;

			$query = "SELECT property, value FROM rep_robj_xcos_prop"
				. " WHERE obj_id=" . $ilDB->quote($this->getId(), 'integer')
				. " AND class=". $ilDB->quote($a_class, 'text');

			$result = $ilDB->query($query);

			$this->class_properties[$a_class] = array();
			while ($row = $ilDB->fetchAssoc($result))
			{
				$this->class_properties[$a_class][$row['property']] = $row['value'];
			}
		}
	}

	/**
	 * Clone all properties for classes of this object
	 * @param int	$a_obj_id	new object id
	 */
	private function cloneProperties($a_obj_id)
	{
		/** @var ilDB $ilDB */
		global $ilDB;

		$query = "
			INSERT INTO rep_robj_xcos_prop(obj_id, class, property, value)
			SELECT ". $ilDB->quote($a_obj_id, 'integer'). ", class, property, value FROM rep_robj_xcos_prop
			WHERE obj_id =" . $ilDB->quote($this->getId(), 'integer');

		$ilDB->manipulate($query);
	}

	/**
	 * Get a user preference stored in the session
	 * @param $a_class
	 * @param $a_key
	 * @param $a_default_value
	 * @return string
	 */
	public function getPreference($a_class, $a_key, $a_default_value)
	{
		return (isset($_SESSION['CombiSubscription'][$a_class][$a_key]) ? $_SESSION['CombiSubscription'][$a_class][$a_key] : $a_default_value);
	}

	/**
	 * Set a user preference stored in the session
	 * @param $a_class
	 * @param $a_key
	 * @param $a_value
	 */
	public function setPreference($a_class, $a_key, $a_value)
	{
		$_SESSION['CombiSubscription'][$a_class][$a_key] = $a_value;
	}


	/**
	 * Set the Assignment Method
	 * @param $a_method
	 */
	public function setMethod($a_method)
	{
		$this->method = $a_method;
	}

	/**
	 * Get the Assignment Method
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}

	/**
	 * Get the Assignment Method Object
	 * @return ilCoSubMethodBase
	 */
	public function getMethodObject()
	{
		if (!isset($this->method_object))
		{
			$this->method_object = $this->getMethodObjectByClass($this->method);
		}
		return $this->method_object;
	}


	/**
	 * Get an assignment method object by classname
	 * @param $a_classname
	 * @return null
	 */
	public function getMethodObjectByClass($a_classname)
	{
		$classfile = $this->plugin->getDirectory().'/classes/methods/class.'.$a_classname.'.php';
		if (is_file($classfile))
		{
			$this->plugin->includeClass('abstract/class.ilCoSubMethodBase.php');
			require_once($classfile);
			return new $a_classname($this, $this->plugin);
		}
		return null;
	}

	/**
	 * Get the available assignment methods
	 * @return ilCoSubMethodBase[]
	 */
	public function getAvailableMethods()
	{
		$this->plugin->includeClass('abstract/class.ilCoSubMethodBase.php');

		$methods = array();
		$classfiles = glob($this->plugin->getDirectory().'/classes/methods/class.*.php');
		if (!empty($classfiles))
		{
			foreach ($classfiles as $file)
			{
				$parts = explode('.',basename($file));
				$classname = $parts[1];
				if (substr($classname, -3) != 'GUI')
				{
					require_once($file);
					$methods[] = new $classname($this, $this->plugin);
				}
			}
		}
		return $methods;
	}

	/**
	 * Get the items assigned to this object (lazy loading)
	 * @return ilCoSubItem[]
	 */
	public function getItems()
	{
		if (!isset($this->items))
		{
			$this->plugin->includeClass('models/class.ilCoSubItem.php');
			$this->items = ilCoSubItem::_getForObject($this->getId());
		}
		return $this->items;
	}

	/**
	 * Get a new (unsaved) item for a target reference
	 * @param $a_ref_id
	 * @return ilCoSubItem
	 */
	public function getItemForTarget($a_ref_id)
	{
		$item = new ilCoSubItem;
		$item->obj_id = $this->getId();
		$item->target_ref_id = $a_ref_id;

		switch (ilObject::_lookupType($a_ref_id, true))
		{
			case 'crs':
				require_once('Modules/Course/classes/class.ilObjCourse.php');
				$course = new ilObjCourse($a_ref_id, true);
				$item->title = $course->getTitle();
				$item->description = $course->getDescription();
				$item->sub_max = $course->getSubscriptionMaxMembers();
				break;

			case 'grp':
				require_once('Modules/Group/classes/class.ilObjGroup.php');
				$group = new ilObjGroup($a_ref_id, true);
				$item->title = $group->getTitle();
				$item->description = $group->getDescription();
				$item->sub_max = $group->getMaxMembers();
				break;
		}
		return $item;
	}

	/**
	 * Get the priorities of all users
	 * @return  array   user_id => item_id => priority
	 */
	public function getPriorities()
	{
		if (!$this->all_priorities_loaded)
		{
			$this->plugin->includeClass('models/class.ilCoSubChoice.php');
			$this->priorities = ilCoSubChoice::_getPriorities($this->getId());
			$this->all_priorities_loaded = true;
		}
		return $this->priorities;
	}


	/**
	 * Get the priorities of a user in this object (lazy loading)
	 * @param $a_user_id
	 * @return array item_id => priority
	 */
	public function getPrioritiesOfUser($a_user_id)
	{
		if (!isset($this->priorities[$a_user_id]))
		{
			$this->priorities[$a_user_id] = array();
			$this->plugin->includeClass('models/class.ilCoSubChoice.php');
			$priorities = ilCoSubChoice::_getPriorities($this->getId(), $a_user_id);
			if (is_array($priorities[$a_user_id]))
			{
				$this->priorities[$a_user_id] = $priorities[$a_user_id];
			}
		}
		return $this->priorities[$a_user_id];
	}


	/**
	 * Get the priorities of all users regarding an item
	 * @param $a_item_id
	 * @return array user_id => priority
	 */
	public function getPrioritiesOfItem($a_item_id)
	{
		$priorities = array();

		foreach ($this->getPriorities() as $user_id => $item_priorities)
		{
			if (isset($item_priorities[$a_item_id]))
			{
				$priorities[$user_id] = $item_priorities[$a_item_id];
			}
		}

		return $priorities;
	}


	/**
	 * Get the counts of priorities for this object
	 * Used to show the bars on the registration screen
	 * This should avoid reading all choices
	 * @return array item_id => priority => count
	 */
	public function getPriorityCounts()
	{
		$this->plugin->includeClass('models/class.ilCoSubChoice.php');
		return ilCoSubChoice::_getPriorityCounts($this->getId());
	}

	/**
	 * Get the runs done for this object
	 * @return ilCoSubRun[]
	 */
	public function getRuns()
	{
		if (!isset($this->runs))
		{
			$this->plugin->includeClass('models/class.ilCoSubRun.php');
			$this->runs = ilCoSubRun::_getForObject($this->getId());
		}
		return $this->runs;
	}

	/**
	 * Get the finished runs done for this object
	 * @return ilCoSubRun[]
	 */
	public function getRunsFinished()
	{
		$runs = array();
		foreach ($this->getRuns() as $run)
		{
			if (isset($run->run_end))
			{
				$runs[] = $run;
			}
		}
		return $runs;
	}

	/**
	 * Get the unfinished runs started for this object
	 * @return ilCoSubRun[]
	 */
	public function getRunsUnfinished()
	{
		$runs = array();
		foreach ($this->getRuns() as $run)
		{
			if (!isset($run->run_end))
			{
				$runs[] = $run;
			}
		}
		return $runs;
	}

	/**
	 * Get an alphabetic label for a run index
	 * @param $index
	 * @return string
	 */
	public function getRunLabel($index)
	{
		for($label = ''; $index >= 0; $index = intval($index / 26) - 1)
		{
			$label = chr($index%26 + 0x41) . $label;
		}
		return $label;
	}


	/**
	 * Get the assignments (lazy loading)
	 * @return array     run_id => user_id => item_id => assign_id
	 *                   (run_id is 0 for the chosen assignments)
	 */
	public function getAssignments()
	{
		if (!isset($this->assignments))
		{
			$this->plugin->includeClass('models/class.ilCoSubAssign.php');
			$this->assignments = ilCoSubAssign::_getForObjectAsArray($this->getId());
		}
		return $this->assignments;
	}


	/**
	 * Get the assignments of a user in a certain run (lazy loading)
	 * @param integer    $a_user_id
	 * @param integer    $a_run_id (default 0 for the chosen assignments)
	 * @return array     item_id => assign_id
	 */
	public function getAssignmentsOfUser($a_user_id, $a_run_id = 0)
	{
		if (!isset($this->assignments))
		{
			$this->plugin->includeClass('models/class.ilCoSubAssign.php');
			$this->assignments = ilCoSubAssign::_getForObjectAsArray($this->getId());
		}
		return isset($this->assignments[$a_run_id][$a_user_id]) ? $this->assignments[$a_run_id][$a_user_id] : array();
	}


	/**
	 * Get the assignments of an item user in a certain run (lazy loading)
	 * @param integer    $a_item_id
	 * @param integer    $a_run_id (default 0 for the chosen assignments)
	 * @return array     user_id => assign_id
	 */
	public function getAssignmentsOfItem($a_item_id, $a_run_id = 0)
	{
		if (!isset($this->assignments))
		{
			$this->plugin->includeClass('models/class.ilCoSubAssign.php');
			$this->assignments = ilCoSubAssign::_getForObjectAsArray($this->getId());
		}

		$assign = array();
		if (isset($this->assignments[$a_run_id]))
		{
			foreach ($this->assignments[$a_run_id] as $user_id => $user_items)
			{
				foreach ($user_items as $item_id => $assign_id)
				{
					if ($item_id == $a_item_id)
					{
						$assign[$user_id] = $assign_id;
					}
				}
			}
		}
		return $assign;
	}


	/**
	 * Get the sum of assignments for an item
	 * @param integer   $a_run_id (default 0 for the chosen assignments)
	 * @return array   	item_id => sum of assignments
	 */
	public function getAssignmentsSums($a_run_id = 0)
	{
		if (!isset($this->assignments))
		{
			$this->plugin->includeClass('models/class.ilCoSubAssign.php');
			$this->assignments = ilCoSubAssign::_getForObjectAsArray($this->getId());
		}

		$sums = array();
		foreach ($this->getItems() as $item)
		{
			$sums[$item->item_id] = 0;
		}

		if (isset($this->assignments[$a_run_id]))
		{
			foreach ($this->assignments[$a_run_id] as $user_id => $user_items)
			{
				foreach ($user_items as $item_id => $assign_id)
				{
					$sums[$item_id]++;
				}
			}
		}

		return $sums;
	}

	/**
	 * Copy the assignments from a run to another run
	 *
	 * @param int	$a_source_run
	 * @param int 	$a_target_run
	 */
	public function copyAssignments($a_source_run, $a_target_run)
	{
		$this->plugin->includeClass('models/class.ilCoSubAssign.php');
		ilCoSubAssign::_deleteForObject($this->getId(), $a_target_run);

		$assignments = $this->getAssignments();
		if (is_array($assignments[$a_source_run]))
		{
			foreach ($assignments[$a_source_run] as $user_id => $items)
			{
				foreach ($items as $item_id => $assign_id)
				{
					$assign = new ilCoSubAssign;
					$assign->obj_id = $this->getId();
					$assign->run_id = $a_target_run;
					$assign->user_id = $user_id;
					$assign->item_id = $item_id;
					$assign->save();
				}
			}
		}
	}


	/**
	 * Get the satisfaction of a user's choices by a certain run
	 * @param integer   $a_user_id
	 * @param integer   $a_run_id (default 0 for the chosen assignments)
	 * @return integer  satisfaction, e.g. self::SATISFIED_FULL
	 */
	public function getSatisfaction($a_user_id, $a_run_id = 0)
	{
		$priorities = $this->getPrioritiesOfUser($a_user_id);
		$assignments = $this->getAssignmentsOfUser($a_user_id, $a_run_id);

		if (count($assignments) < $this->getMethodObject()->getNumberAssignments())
		{
			return self::SATISFIED_NOT;			// not enough assignments
		}

		foreach ($assignments as $item_id => $assign_id)
		{
			if (!isset($priorities[$item_id]))
			{
				return self::SATISFIED_NOT;		// assigned to an item without priority
			}
			elseif ($priorities[$item_id] > 0)
			{
				return self::SATISFIED_MEDIUM; 	// assigned to item with lower priority
			}
		}

		return self::SATISFIED_FULL;			// all assignments are highest priority
	}


	/**
	 * Remove all user related data choices, runs and assignments
	 */
	public function removeUserData()
	{
		$this->plugin->includeClass('models/class.ilCoSubAssign.php');
		$this->plugin->includeClass('models/class.ilCoSubChoice.php');
		$this->plugin->includeClass('models/class.ilCoSubRun.php');

		ilCoSubAssign::_deleteForObject($this->getId());
		ilCoSubChoice::_deleteForObject($this->getId());
		ilCoSubRun::_deleteForObject($this->getId());
	}
}
