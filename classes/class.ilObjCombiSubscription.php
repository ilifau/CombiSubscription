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
	const SATISFIED_OVER = 3;
	const SATISFIED_CONFLICT = -1;
	const SATISFIED_EMPTY = -2;
	# endregion

	# region class variables

	protected $online = false;
	protected $explanation = '';
	protected $sub_start = null;
	protected $sub_end = null;
	protected $show_bars = true;
	protected $pre_select = false;
	protected $min_choices = 0;
	protected $method = 'ilCoSubMethodRandom';
	protected $auto_process = false;
	protected $last_process = null;
	protected $class_properties = array();

	/** @var  ilCombiSubscriptionPlugin */
	public $plugin;

	/** @var  ilCoSubMethodBase | null */
	protected $method_object;

	/** @var ilCoSubItem[] | null (indexed by item_id) */
	protected $items;

	/** @var ilCoSubCategory[] | null  (indexed by cat_id) */
	protected $categories;

	/** @var  ilCoSubUser[] | null  (indexed by user_id) */
	protected $users;

	/** @var  ilCoSubRun[] | null  (numerically indexed) */
	protected $runs;

	/** @var  array     user_id => item_id => priority */
	protected $priorities;

	/** @var  array 	item_id => item_id[] */
	protected $conflicts;

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
			"(obj_id, is_online, explanation, sub_start, sub_end, show_bars, pre_select, min_choices, method, auto_process, last_process) VALUES (".
			$ilDB->quote($this->getId(), 'integer').','.
			$ilDB->quote(0, 'integer').','.
			$ilDB->quote(null, 'text').','.
			$ilDB->quote($dummyDate->get(IL_CAL_DATETIME), 'text').','.
			$ilDB->quote($dummyDate->get(IL_CAL_DATETIME), 'text').','.
			$ilDB->quote($this->getShowBars(), 'integer').','.
			$ilDB->quote($this->getPreSelect(), 'integer').','.
			$ilDB->quote($this->getMinChoices(), 'integer').','.
			$ilDB->quote('ilCoSubMethodRandom', 'text').','.
			$ilDB->quote($this->getAutoProcess(), 'integer').','.
			$ilDB->quote(null, 'text').
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
			$this->setPreSelect((bool) $rec['pre_select']);
			$this->setMinChoices($rec['min_choices']);
			$this->setMethod($rec['method']);
			$this->setAutoProcess($rec['auto_process']);
			$this->setLastProcess(empty($rec['last_process']) ? null : new ilDateTime($rec['last_process'], IL_CAL_DATETIME));
		}
		else
		{
			$this->setOnline(false);
			$this->setSubscriptionStart(new ilDateTime(time(), IL_CAL_UNIX));
			$this->setSubscriptionEnd(new ilDateTime(time(), IL_CAL_UNIX));
			$this->setShowBars(true);
			$this->setPreSelect(false);
			$this->setMinChoices(0);
			$this->setMethod('ilCoSubMethodRandom');
			$this->setAutoProcess(false);
			$this->setLastProcess(null);
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
			" pre_select = ".$ilDB->quote($this->getPreSelect(), 'integer').','.
			" method = ".$ilDB->quote($this->getMethod(),'text').','.
			" auto_process = ". $ilDB->quote($this->getAutoProcess(), 'integer'). ','.
			" last_process = ". $ilDB->quote(is_object($this->getLastProcess()) ? $this->getLastProcess()->get(IL_CAL_DATETIME) : null, 'timestamp').
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

		// clone the categories
		$cat_map = array();
		foreach ($this->getCategories() as $category)
		{
			$cat_id = $category->cat_id;
			$clone = $category->saveClone($new_obj->getId());
			$cat_map[$cat_id] = $clone->cat_id;
		}

		// clone the items
		foreach ($this->getItems() as $item)
		{
			$item->saveClone($new_obj->getId(), $cat_map);
		}

		if ($this->plugin->withStudyCond())
		{
			require_once('Services/Membership/classes/class.ilSubscribersStudyCond.php');
			ilSubscribersStudyCond::_clone($this->getId(), $new_obj->getId());
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
	public function 	getSubscriptionStart()
	{
		return $this->sub_start;
	}

	/**
	 * Set Subscription End
	 * @param $a_sub_end
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
	 * @return bool
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
	 * Get if choices shouls be pre-selected for the first time
	 * @return bool
	 */
	public function getPreSelect()
	{
		return $this->pre_select;
	}


	/**
	 * Set if choices shouls be pre-selected for the first time
	 * @param bool $a_pre_select
	 */
	public function setPreSelect($a_pre_select)
	{
		$this->pre_select = $a_pre_select;
	}

	/**
	 * Get auto processing after subscription end
	 * @return bool
	 */
	public function getAutoProcess()
	{
		return $this->auto_process;
	}

	/**
	 * Set auto processing after subscription end
	 * @param bool $a_val
	 */
	public function setAutoProcess($a_val)
	{
		$this->auto_process = $a_val;
	}


	/**
	 * Get the last processing time
	 * @return ilDateTime|null
	 */
	public function getLastProcess()
	{
		return $this->last_process;
	}

	/**
	 * Set the last processing tile
	 * @param ilDateTime|null $a_val
	 */
	public function setLastProcess($a_val = null)
	{
		$this->last_process = $a_val;
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
	 * Get the categories defined in this object (lazy loading)
	 * @return ilCoSubCategory[]	indexed by cat_id
	 */
	public function getCategories()
	{
		if (!isset($this->categories))
		{
			$this->plugin->includeClass('models/class.ilCoSubCategory.php');
			$this->categories = ilCoSubCategory::_getForObject($this->getId());
		}
		return $this->categories;
	}

	/**
	 * Get the assignment limits of categories
	 * Only categories with limits are included
	 * @return array	$cat_id => $limit
	 */
	public function getCategoryLimits()
	{
		$limits = array();
		foreach ($this->getCategories() as $cat_id => $category)
		{
			if (!empty($category->max_assignments))
			{
				$limits[$cat_id] = $category->max_assignments;
			}
		}
		return $limits;
	}


	/**
	 * Get the items assigned to this object (lazy loading)
	 * @var string $filter		'selectable'
	 * @return ilCoSubItem[]	indexed by item_id
	 */
	public function getItems($filter = '')
	{
		if (!isset($this->items))
		{
			$this->plugin->includeClass('models/class.ilCoSubItem.php');
			$this->items = ilCoSubItem::_getForObject($this->getId());
		}

		switch($filter)
		{
			case 'selectable':
				$items = array();
				foreach ($this->items as $item_id => $item)
				{
					if ($item->selectable)
					{
						$items[$item_id] = $item;
					}
				}
				return $items;

			default:
				return $this->items;
		}
	}

	/**
	 * Get the items grouped by category
	 * @var string $filter		'selectable'
	 * @return array	cat_id => item_id => ilCoSubItem
	 */
	public function getItemsByCategory($filter = '')
	{
		$items = array(
			0 => array()	// index for items without category
		);
		foreach ($this->getCategories() as $category)
		{
			$items[$category->cat_id] = array();
		}

		foreach ($this->getItems($filter)as $item)
		{
			$items[(int) $item->cat_id][$item->item_id] = $item;
		}

		return $items;
	}


	/**
	 * Get a list conflicting items
	 * @return array	item_id => item_id[]
	 */
	public function getItemsConflicts()
	{
		$buffer = max($this->getMethodObject()->getOutOfConflictTime(), $this->plugin->getOutOfConflictTime());
		$tolerance = $this->getMethodObject()->getToleratedConflictPercentage();

		if (!isset($this->conflicts))
		{
			$this->conflicts = array();
			foreach ($this->getItems() as $item1_id => $item1)
			{
				$this->conflicts[$item1_id] = array();
				foreach ($this->getItems() as $item2_id => $item2)
				{
					if ($item1_id != $item2_id && ilCoSubItem::_haveConflict($item1, $item2, $buffer, $tolerance))
					{
						$this->conflicts[$item1_id][] = $item2_id;
					}
				}
			}
		}
		return $this->conflicts;
	}

	/**
	 * Check if items have mutual conflicts
	 * @param int[] $a_item_ids
	 * @return bool
	 */
	public function itemsHaveConflicts($a_item_ids)
	{
		$conflicts = $this->getItemsConflicts();

		foreach ($a_item_ids as $item_id)
		{
			if (empty($conflicts[$item_id]))
			{
				return false;
			}
			$found = array_intersect($conflicts[$item_id], $a_item_ids);
			if (!empty($found))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a list of items exceeds the assignment limits defined by their categories
	 * @param int[] $a_item_ids
	 * @return bool
	 */
	public function itemsOverCategoryLimits($a_item_ids)
	{
		$items = $this->getItems();
		$limits = $this->getCategoryLimits();

		$catcounts = array();
		foreach ($a_item_ids as $item_id)
		{
			if (!empty($items[$item_id]))
			{
				$item = $items[$item_id];
				if (!empty($item->cat_id))
				{
					$catcounts[$item->cat_id]++;
				}
			}
		}
		foreach ($catcounts as $cat_id => $count)
		{
			if (isset($limits[$cat_id]) && $count > $limits[$cat_id])
			{
				return true;
			}
		}
		return false;
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

			foreach($this->getUsers() as $userObj)
			{
				if (!isset($this->priorities[$userObj->user_id]))
				{
					$this->priorities[$userObj->user_id] = array();
				}
			}
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
	 * @param bool		 $a_force 	force the reading of assignments
	 * @return array     run_id => user_id => item_id => assign_id
	 *                   (run_id is 0 for the chosen assignments)
	 */
	public function getAssignments($a_force = false)
	{
		if (!isset($this->assignments) || $a_force)
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
	 * Assignments of fixed users are kept
	 *
	 * @param int	$a_source_run
	 * @param int 	$a_target_run
	 */
	public function copyAssignments($a_source_run, $a_target_run)
	{
		$fixed_ids = $this->getFixedUserIds();

		$this->plugin->includeClass('models/class.ilCoSubAssign.php');
		ilCoSubAssign::_deleteForObject($this->getId(), $a_target_run, $fixed_ids);

		$assignments = $this->getAssignments(true);
		if (is_array($assignments[$a_source_run]))
		{
			foreach ($assignments[$a_source_run] as $user_id => $items)
			{
				if (in_array($user_id, $fixed_ids))
				{
					continue;
				}

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
	 * Get a single user object (may not be saved yet)
	 * Used for registration
	 * @param	int	$a_user_id
	 * @return	ilCoSubUser
	 */
	public function getUser($a_user_id)
	{
		$this->plugin->includeClass('models/class.ilCoSubUser.php');

		$userObj = ilCoSubUser::_getById($this->getId(), $a_user_id);
		if (!isset($userObj))
		{
			$userObj = new ilCoSubUser();
			$userObj->obj_id = $this->getId();
			$userObj->user_id = $a_user_id;
		}

		return $userObj;
	}

	/**
	 * Get a list of user objects (indexed by user_id)
     * ilCoSubUser objects will be created but not saved for new users
     *
	 * @param	array	$a_user_ids (optional)
	 * @param bool		$a_force 	force the reading of users
	 * @return	ilCoSubUser[]
	 */
	public function getUsers($a_user_ids = array(), $a_force = false)
	{
		if (!isset($this->users) || $a_force)
		{
			$this->plugin->includeClass('models/class.ilCoSubUser.php');
			$this->users = ilCoSubUser::_getForObject($this->getId());
		}

		if (!empty($a_user_ids))
		{
			$users = array();
			foreach ($a_user_ids as $user_id)
			{
				if (isset($this->users[$user_id]))
				{
					$users[$user_id] = $this->users[$user_id];
				}
				else
				{
					$userObj = new ilCoSubUser;
					$userObj->obj_id = $this->getId();
					$userObj->user_id = $user_id;
					$users[$user_id] = $userObj;
				}
			}
			return $users;
		}

		return $this->users;
	}

	/**
	 * Get a list of user objects (indexed by user_id) that are fixed or satisfy the studidata condition
     * @param bool $a_with_fixed    add fixed users (regardless of condition)
	 * @return	array
	 */
	public function getUsersForStudyCond($a_with_fixed = true)
	{
		if (!$this->plugin->withStudyCond())
		{
			return $this->getUsers();
		}

		require_once('Services/Membership/classes/class.ilSubscribersStudyCond.php');
		$conditionsdata = ilSubscribersStudyCond::_getConditionsData($this->getId());
		if (!count($conditionsdata))
		{
			return $this->getUsers();
		}

		$users = array();
		foreach ($this->getUsers() as $user_id => $userObj)
		{
			// always take the fixed users
			if ($a_with_fixed && $userObj->is_fixed)
			{
				$users[$user_id] = $userObj;
				continue;
			}

			$studydata = ilStudyAccess::_getStudyData($user_id);
			if (!count($studydata))
			{
				continue;
			}

			if (ilStudyAccess::_checkConditions($conditionsdata, $studydata))
			{
				$users[$user_id] = $userObj;
			}
		}

		return $users;
	}

	/**
	 * Get details of seelcted users
	 * @param $a_user_ids
	 * @return array	user_id => assoc details
	 */
	public function getUserDetails($a_user_ids)
	{
		// query for users
		include_once("Services/User/classes/class.ilUserQuery.php");
		$user_query = new ilUserQuery();
		$user_query->setUserFilter($a_user_ids);
		$user_query->setLimit($this->plugin->getUserQueryLimit());
		$user_query_result = $user_query->query();

		$details = array();
		foreach ($user_query_result['set'] as $user)
		{
			$user['showname'] = $user['lastname'] . ', ' . $user['firstname'];
			$details[$user['usr_id']] = $user;
		}
		return $details;
	}

	/**
	 * Get the satisfaction of a user's choices by a certain run
	 * @param integer   $a_user_id
	 * @param integer   $a_run_id (default 0 for the chosen assignments)
	 * @return integer  satisfaction, e.g. self::SATISFIED_FULL
	 */
	public function getUserSatisfaction($a_user_id, $a_run_id = 0)
	{
		$priorities = $this->getPrioritiesOfUser($a_user_id);
		$assignments = $this->getAssignmentsOfUser($a_user_id, $a_run_id);

		if (count($assignments) > $this->getMethodObject()->getNumberAssignments())
		{
			return self::SATISFIED_OVER;
		}
		if ($this->itemsOverCategoryLimits(array_keys($assignments)))
		{
			return self::SATISFIED_OVER;
		}
        if ($this->itemsHaveConflicts(array_keys($assignments)))
        {
            return self::SATISFIED_CONFLICT;
        }
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
	 * Get the user ids of fixed users
	 * @return int[]
	 */
	public function getFixedUserIds()
	{
		$fixed_ids = array();
		foreach ($this->getUsers() as $user_id => $user_obj)
		{
			if ($user_obj->is_fixed)
			{
				$fixed_ids[] = $user_id;
			}
		}
		return $fixed_ids;
	}

	/**
	 * Set all users with assignments to 'fixed'
	 */
	public function fixAssignedUsers()
	{
		foreach ($this->getUsers() as $subUser)
		{
			if ($this->getAssignmentsOfUser($subUser->user_id))
			{
				$subUser->is_fixed = true;
				$subUser->save();
			}
		}
	}

    /**
     * Set specific users to 'fixed'
     * @param array $a_user_ids
     */
	public function fixUsers($a_user_ids = array())
    {
        foreach ($this->getUsers() as $subUser)
        {
            if (in_array($subUser->user_id, $a_user_ids))
            {
                $subUser->is_fixed = true;
                $subUser->save();
            }
        }
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


	/**
	 * Get a list of reference ids that are due for an auto procesing
	 */
	public static function _getRefIdsForAutoProcess()
	{
		global $ilDB;

		$query = "SELECT obj_id FROM rep_robj_xcos_data WHERE auto_process = 1 AND sub_end < NOW()";
		$result = $ilDB->query($query);

		$ref_ids = array();
		while ($row = $ilDB->fetchAssoc($result))
		{
			foreach (ilObject::_getAllReferences($row['obj_id']) as $ref_id)
			{
				if (!ilObject::_isInTrash($ref_id))
				{
					$ref_ids[] = $ref_id;
					break;
				}
			}
		}

		return $ref_ids;
	}


	/**
	 * Handle the auto processing of an object
	 */
	public function handleAutoProcess()
	{
		$this->setAutoProcess(false);
		$this->setLastProcess(new ilDateTime(time(), IL_CAL_UNIX));
		$this->update();

		// adjust subscribe users and maximum subscriptions
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets_obj = new ilCombiSubscriptionTargets($this, $this->plugin);
		$targets_obj->syncFromTargetsBeforeCalculation();


		// calculate the assignments
		$run = $this->getMethodObject()->getBestCalculationRun($this->plugin->getNumberOfTries());
		if (!isset($run))
		{
			return false;
		}

		// copy the calculated assignments of the run to the current assignments
		$this->copyAssignments($run->run_id, 0);
		$this->setClassProperty('ilCoSubAssignmentsGUI', 'source_run', $run->run_id);
		$this->getAssignments(true);
		$this->fixAssignedUsers();

		// remove conflicting choices from other combined subscriptions
		$this->plugin->includeClass('class.ilCombiSubscriptionConflicts.php');
		$conflictsObj = new ilCombiSubscriptionConflicts($this, $this->plugin);
		$removedConflicts = $conflictsObj->removeConflicts();

		// notify users about calculation result
		$this->plugin->includeClass('class.ilCombiSubscriptionMailNotification.php');
		$notification = new ilCombiSubscriptionMailNotification();
		$notification->setPlugin($this->plugin);
		$notification->setObject($this);
		$notification->sendAssignments($removedConflicts);

		// add users as members or subscribers
		$this->handleAutoProcessTargets();

		// show the transfer time in the assignments gui
		$this->setClassProperty('ilCoSubAssignmentsGUI', 'transfer_time', time());

		return true;
	}

	/**
	 * Add users as members or subscribers to the target objects
	 * Set the new object configuration for the target objects
	 */
	public function handleAutoProcessTargets()
	{
		$this->plugin->includeClass('models/class.ilCoSubTargetsConfig.php');
		$config = new ilCoSubTargetsConfig($this);
		$config->readFromObject();

		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets_obj = new ilCombiSubscriptionTargets($this, $this->plugin);
		$targets_obj->filterUntrashedTargets();
		$targets_obj->applyTargetsConfig($config); 			// may change waiting list settings
		$targets_obj->addAssignedUsersAsMembers();
		$targets_obj->addNonAssignedUsersAsSubscribers(); 	// should be called with new waiting list settings

		// new subscription period for second round in combined subscription
		if ($config->set_sub_type && $config->sub_type == ilCoSubTargetsConfig::SUB_TYPE_COMBI
			&& $config->set_sub_period && $config->sub_period_end > time())
		{
			$this->setSubscriptionEnd(new ilDateTime($config->sub_period_end, IL_CAL_UNIX));
			$this->setAutoProcess(true);
			$this->update();
		}
	}
}
