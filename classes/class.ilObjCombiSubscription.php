<?php

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
    const SATISFIED_UNKNOWN = -3;
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

	protected ?ilCoSubMethodBase $method_object;

	/** ilCoSubItem[] | null (indexed by item_id) */
	protected ?array $items;

	/** ilCoSubCategory[] | null  (indexed by cat_id) */
	protected ?array $categories;

	/** ilCoSubUser[] | null  (indexed by user_id) */
	protected ?array $users;

	/** ilCoSubRun[] | null  (numerically indexed) */
	protected ?array $runs;

	/** user_id => item_id => priority */
	protected array $priorities;

	/** item_id => item_id[] */
	protected array $conflicts;

	/** the priorities of all users are loaded */
	protected bool $all_priorities_loaded = false;

	/** run_id => user_id => item_id => assign_id (run_id is 0 for the chosen assignments) */
	protected array $assignments;

	# endregion

	public function __construct(int $a_ref_id = 0)
	{
		parent::__construct($a_ref_id);
	}

	public function getPlugin(): ilCombiSubscriptionPlugin
	{
		return parent::getPlugin();
	}

	/**
	* Get type
	*/
	final public function initType(): void
	{
		$this->setType('xcos');
	}
	
	/**
	* Create object
	*/
	protected function doCreate(bool $clone_mode = false): void
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
	protected function doRead(): void
	{
		global $ilDB;
		
		$set = $ilDB->query("SELECT * FROM rep_robj_xcos_data ".
			" WHERE obj_id = ".$ilDB->quote($this->getId(), 'integer')
			);
		if ($rec = $ilDB->fetchAssoc($set))
		{
			$this->setOnline($rec['is_online']);
			$this->setExplanation((string) $rec['explanation']);
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
	protected function doUpdate(): void
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
	protected function doDelete(): void
	{
		global $DIC;
		
		$DIC->database()->manipulate("DELETE FROM rep_robj_xcos_data WHERE ".
			" obj_id = ".$DIC->database()->quote($this->getId(), 'integer')
			);

		if ($this->getPlugin()->hasFauService()) {
            $DIC->fau()->cond()->soft()->deleteConditionsOfObject($this->getId());
        }
	}
	
	/**
	 * Do Cloning
	 */
	function doCloneObject(ilObject2 $new_obj, int $a_target_id, ?int $a_copy_id = null): void
	{
        global $DIC;

		$new_obj->setOnline(false);
		$new_obj->setExplanation($this->getExplanation());
		$new_obj->setSubscriptionStart($this->getSubscriptionStart());
		$new_obj->setSubscriptionEnd($this->getSubscriptionEnd());
		$new_obj->setPreSelect($this->getPreSelect());
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
        $item_map = array();
		foreach ($this->getItems() as $item)
		{
            $item_id = $item->item_id;
			$clone = $item->saveClone($new_obj->getId(), $cat_map);
            $item_map[$item_id] = $clone->item_id;
		}

		if ($this->getPlugin()->hasFauService())
		{
            $DIC->fau()->cond()->soft()->cloneConditions($this->getId(), $new_obj->getId());
		}

        if ($this->getPlugin()->getCloneWithChoices()) {

            // clone the users
            foreach ($this->getUsers() as $user)
            {
                $user->saveClone($new_obj->getId());
            }
            // clone the choices
            foreach ($this->getChoices() as $choice)
            {
                $choice->saveClone($new_obj->getId(), $item_map);
            }
        }

	}

	/**
	* Set online
	*
	* @param	boolean		$a_val
	*/
	public function setOnline(bool $a_val): void
	{
		$this->online = $a_val;
	}
	
	/**
	* Get online
	*
	* @return	boolean		online
	*/
	public function getOnline(): bool
	{
		return $this->online;
	}

	/**
	 * Set explanation
	 *
	 * @param	string		$a_val
	 */
	public function setExplanation(string $a_val): void
	{
		$this->explanation = $a_val;
	}

	/**
	 * Get explanation
	 *
	 * @return	string		explanation
	 */
	public function getExplanation(): string
	{
		return (string) $this->explanation;
	}


	/**
	 * Set Subscription Start
	 * @param ilDateTime    $a_sub_start
	 */
	public function setSubscriptionStart(ilDateTime $a_sub_start): void
	{
		$this->sub_start = $a_sub_start;
	}

	/**
	 * Get Subscription Start
	 * @return ilDateTime
	 */
	public function getSubscriptionStart(): ilDateTime
	{
		return $this->sub_start;
	}

	/**
	 * Set Subscription End
	 * @param $a_sub_end
	 */
	public function setSubscriptionEnd(ilDateTime $a_sub_end): void
	{
		$this->sub_end = $a_sub_end;
	}

	/**
	 * Get Subscription End
	 * @return ilDateTime
	 */
	public function getSubscriptionEnd(): ilDateTime
	{
		return $this->sub_end;
	}

	/**
	 * Check if the current date is before the subscription period
	 */
	public function isBeforeSubscription(): bool
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
	public function isAfterSubscription(): bool
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
	public function getShowBars(): bool
	{
		return $this->show_bars;
	}


	/**
	 * Set if bars should be shown on the registration screen
	 * @param bool	$a_show_bars
	 */
	public function setShowBars(bool $a_show_bars): void
	{
		$this->show_bars = (bool) $a_show_bars;
	}

	/**
	 * Get if choices shouls be pre-selected for the first time
	 * @return bool
	 */
	public function getPreSelect(): bool
	{
		return $this->pre_select;
	}


	/**
	 * Set if choices shouls be pre-selected for the first time
	 * @param bool $a_pre_select
	 */
	public function setPreSelect(bool $a_pre_select): void
	{
		$this->pre_select = $a_pre_select;
	}

	/**
	 * Get auto processing after subscription end
	 * @return bool
	 */
	public function getAutoProcess(): bool
	{
		return $this->auto_process;
	}

	/**
	 * Set auto processing after subscription end
	 * @param bool $a_val
	 */
	public function setAutoProcess(bool $a_val): void
	{
		$this->auto_process = $a_val;
	}


	/**
	 * Get the last processing time
	 * @return ilDateTime|null
	 */
	public function getLastProcess(): ?ilDateTime
	{
		return $this->last_process;
	}

	/**
	 * Set the last processing tile
	 * @param ilDateTime|null $a_val
	 */
	public function setLastProcess(?ilDateTime $a_val = null): void
	{
		$this->last_process = $a_val;
	}


	/**
	 * Get the minimum choices that must be selected for registration
	 * @return int
	 */
	public function getMinChoices(): int
	{
		return (int) $this->min_choices;
	}


	/**
	 * Set the minimum choices that must be selected for registration
	 * @param	int		$a_choices
	 */
	public function setMinChoices(int $a_choices): void
	{
		$this->min_choices = (int) $a_choices;
	}


	/**
	 * Get a property for this class
	 * @param   string  $a_key
	 * @param   string  $a_default_value
	 * @return string	value
	 */
	public function getProperty(string $a_key, string $a_default_value): string
	{
		return $this->getClassProperty(get_class($this), $a_key, $a_default_value);
	}

	/**
	 * Set a property for this class
	 * @param   string  $a_key
	 * @param   string  $a_value
	 */
	public function setProperty(string $a_key, string $a_value): void
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
	public function getClassProperty(string $a_class, string $a_key, string $a_default_value): string
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
	public function setClassProperty(string $a_class, string $a_key, string $a_value): void
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
	private function readClassProperties(string $a_class): void
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
	private function cloneProperties(int $a_obj_id): void
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
	public function getPreference(string $a_class, string $a_key, string $a_default_value): string
	{
		return (isset($_SESSION['CombiSubscription'][$a_class][$a_key]) ? $_SESSION['CombiSubscription'][$a_class][$a_key] : $a_default_value);
	}

	/**
	 * Set a user preference stored in the session
	 * @param $a_class
	 * @param $a_key
	 * @param $a_value
	 */
	public function setPreference(string $a_class, string $a_key, string $a_value): void
	{
		$_SESSION['CombiSubscription'][$a_class][$a_key] = $a_value;
	}


	/**
	 * Set the Assignment Method
	 * @param $a_method
	 */
	public function setMethod(string $a_method): void
	{
		$this->method = $a_method;
	}

	/**
	 * Get the Assignment Method
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * Get the Assignment Method Object
	 * @return ilCoSubMethodBase
	 */
	public function getMethodObject(): ilCoSubMethodBase
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
	public function getMethodObjectByClass(string $a_classname)
	{
		$classfile = $this->getPlugin()->getDirectory().'/classes/methods/class.'.$a_classname.'.php';
		if (is_file($classfile))
		{
			require_once($classfile);
			return new $a_classname($this, $this->getPlugin());
		}
		return null;
	}

	/**
	 * Get the available assignment methods
	 * @return ilCoSubMethodBase[]
	 */
	public function getAvailableMethods(): array
	{
		$methods = array();
		$classfiles = glob($this->getPlugin()->getDirectory().'/classes/methods/class.*.php');
		if (!empty($classfiles))
		{
			foreach ($classfiles as $file)
			{
				$parts = explode('.',basename($file));
				$classname = $parts[1];
				if (substr($classname, -3) != 'GUI')
				{
					require_once($file);
					$methods[] = new $classname($this, $this->getPlugin());
				}
			}
		}
		return $methods;
	}

	/**
	 * Get the categories defined in this object (lazy loading)
	 * @return ilCoSubCategory[]	indexed by cat_id
	 */
	public function getCategories(): array
	{
		if (!isset($this->categories))
		{
			$this->categories = ilCoSubCategory::_getForObject($this->getId());
		}
		return $this->categories;
	}

	/**
	 * Get the assignment limits of categories
	 * Only categories with limits are included
	 * @return array	$cat_id => $limit
	 */
	public function getCategoryLimits(): array
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
	public function getItems(string $filter = ''): array
	{
		if (!isset($this->items))
		{
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
	public function getItemsByCategory(string $filter = ''): array
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
	public function getItemsConflicts(): array
	{
		$buffer = max($this->getMethodObject()->getOutOfConflictTime(), $this->getPlugin()->getOutOfConflictTime());
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
	 * Get a list of items with conflicts
     * Result is a list of item pairs
     * 
	 * @param int[] $a_item_ids
	 * @return array [[item_id1, item_id2], ..]
	 */
	public function getConflictPairs(array $a_item_ids): array
	{
        $conflicts = $this->getItemsConflicts();

        $pairs = [];
        foreach ($a_item_ids as $item_id1) {
            foreach ($conflicts[$item_id1] ?? [] as $item_id2) {
                if (in_array($item_id2, $a_item_ids) && !isset($pairs['#'.$item_id2.'#'.$item_id1])) {
                    $pairs['#'.$item_id1.'#'.$item_id2] = [$item_id1, $item_id2];
                }
            }
        }
        return array_values($pairs);
	}

	/**
	 * Check if a list of assigned item_ids exceeds the assignment limits defined by their categories
	 * @param int[] $a_item_ids assigned item ids
	 * @return array    cat_id => number of assigned item_ids
	 */
	public function categoriesOverAssignmentLimit(array $a_item_ids): array
	{
		$items = $this->getItems();
		$limits = $this->getCategoryLimits();

		$catcounts = [];
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
        
        $exceeded = [];
		foreach ($catcounts as $cat_id => $count)
		{
			if (isset($limits[$cat_id]) && $count > $limits[$cat_id])
			{
                $exceeded[$cat_id] = $count;
			}
		}
		return $exceeded;
	}


    /**
     * Get the choices of an object
     * @return ilCoSubChoice[] indexed by choice_id
     */
    public function getChoices(): array
    {
        return ilCoSubChoice::_getForObject($this->getId());
    }


	/**
	 * Get the priorities of all users
	 * @return  array   user_id => item_id => priority
	 */
	public function getPriorities(): array
	{
		if (!$this->all_priorities_loaded)
		{
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
     * Get all priorities for items where the restrictions are passed
     *
     * @return  array   user_id => item_id => priority
     */
    public function getPrioritiesWithPassedRestrictions(): array
    {
        if (!$this->getPlugin()->hasFauService()) {
            return $this->getPriorities();
        }

        global $DIC;
        $hardRestrictions = $DIC->fau()->cond()->hard();
        $items = $this->getItems();

        $priorities = [];
        foreach ($this->getPriorities() as $user_id => $item_priorities) {
            foreach ($item_priorities as $item_id => $priority) {
                if (isset($items[$item_id])) {
                    $item = $items[$item_id];
                    $import_id = \FAU\Study\Data\ImportId::fromString($item->import_id);
                    if ($hardRestrictions->checkByImportId($import_id, $user_id)) {
                        $priorities[$user_id][$item_id] = $priority;
                    }
                }
            }
        }
        return $priorities;
    }


	/**
	 * Get the priorities of a user in this object (lazy loading)
	 * @param $a_user_id
	 * @return array item_id => priority
	 */
	public function getPrioritiesOfUser(int $a_user_id): array
	{
		if (!isset($this->priorities[$a_user_id]))
		{
			$this->priorities[$a_user_id] = array();
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
	public function getPrioritiesOfItem(int $a_item_id): array
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
	public function getPriorityCounts(): array
	{
		return ilCoSubChoice::_getPriorityCounts($this->getId());
	}

	/**
	 * Get the runs done for this object
	 * @return ilCoSubRun[]
	 */
	public function getRuns(): array
	{
		if (!isset($this->runs))
		{
			$this->runs = ilCoSubRun::_getForObject($this->getId());
		}
		return $this->runs;
	}

	/**
	 * Get the finished runs done for this object
	 * @return ilCoSubRun[]
	 */
	public function getRunsFinished(): array
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
	public function getRunsUnfinished(): array
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
	public function getRunLabel(int $index): string
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
	public function getAssignments(bool $a_force = false): array
	{
		if (!isset($this->assignments) || $a_force)
		{
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
	public function getAssignmentsOfUser(int $a_user_id, int $a_run_id = 0): array
	{
		if (!isset($this->assignments))
		{
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
	public function getAssignmentsOfItem(int $a_item_id, int $a_run_id = 0): array
	{
		if (!isset($this->assignments))
		{
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
	public function getAssignmentsSums(int $a_run_id = 0): array
	{
		if (!isset($this->assignments))
		{
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
     * @param bool $a_keep_fixed
	 */
	public function copyAssignments(int $a_source_run, int $a_target_run, bool $a_keep_fixed = true): void
	{
        $fixed_ids = ($a_keep_fixed ?  $this->getFixedUserIds() : []);

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
	public function getUser(int $a_user_id): ilCoSubUser
	{
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
	 * @return	ilCoSubUser[]       indexed by user_id
	 */
	public function getUsers(array $a_user_ids = [], bool $a_force = false): array
	{
		if (!isset($this->users) || $a_force)
		{
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
	 * @return	array $user_id => $user data object
	 */
	public function getUsersForStudyCond(bool $a_with_fixed = true): array
	{
        global $DIC;

		if (!$this->getPlugin()->hasFauService())
		{
			return $this->getUsers();
		}

		if (!$DIC->fau()->cond()->repo()->checkObjectHasSoftCondition($this->getId()))
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

            if (!$DIC->fau()->user()->repo()->checkUserHasPerson($user_id))
            {
				continue;
			}

			if ($DIC->fau()->cond()->soft()->check($this->getId(), $user_id))
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
	public function getUserDetails(array $a_user_ids): array
	{
		// query for users
		$user_query = new ilUserQuery();
		$user_query->setUserFilter($a_user_ids);
		$user_query->setLimit($this->getPlugin()->getUserQueryLimit());
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
	public function getUserSatisfaction(int $a_user_id, int $a_run_id = 0): int
	{
        $details = $this->getUserSatisfactionDetails($a_user_id, $a_run_id = 0);
        if (isset($details['total_assignments_exceeded'])) {
            return self::SATISFIED_OVER;
        }
        if (isset($details['category_limits_exceeded'])) {
            return self::SATISFIED_OVER;
        }
        if (isset($details['assignments_with_conflicts'])) {
            return self::SATISFIED_CONFLICT;
        } 
        if (isset($details['total_assignments_not_reached'])) {
            return self::SATISFIED_NOT;
        }
        if (isset($details['assignments_not_chosen'])) {
            return self::SATISFIED_NOT;
        }
        if (isset($details['assignments_with_lower_priority'])) {
            return self::SATISFIED_MEDIUM;
        }
        if (isset($details['assignments_with_highest_priority'])) {
            return self::SATISFIED_FULL;
        }
        return self::SATISFIED_UNKNOWN;
	}

    /**
     * Get the satisfaction of a user's choices by a certain run
     * @param integer   $a_user_id
     * @param integer   $a_run_id (default 0 for the chosen assignments)
     * @return array  
     */
    public function getUserSatisfactionDetails(int $a_user_id, int $a_run_id = 0): array
    {
        /** @var ilCoSubMethodBase $method */
        $method = $this->getMethodObject();
        $priorities = $this->getPrioritiesOfUser($a_user_id);
        $assignments = $this->getAssignmentsOfUser($a_user_id, $a_run_id);
        $categories = $this->getCategories();
        $items = $this->getItems();

        $details = [];
        if (count($assignments) > $method->getNumberAssignments()) {
            $list = [
                sprintf($this->getPlugin()->txt('total_assignments_details'), $method->getNumberAssignments(), count($assignments))
            ];
            $details['total_assignments_exceeded'] = [
                'status' => self::SATISFIED_OVER,
                'text' => $this->getPlugin()->txt('total_assignments_exceeded'),
                'list' => $list
            ];
        }
        if (!empty($exceeded = $this->categoriesOverAssignmentLimit(array_keys($assignments)))) {
            $list = [];
            foreach ($exceeded as $cat_id => $num_assigned) {
                $category = $categories[$cat_id];
                $list[] = sprintf($this->getPlugin()->txt('category_limits_exceeded_details'), 
                    $category->title, $category->max_assignments, $num_assigned);
            }
            $details['category_limits_exceeded'] = [
                'status' => self::SATISFIED_OVER,
                'text' => $this->getPlugin()->txt('category_limits_exceeded'),
                'list' => $list
            ];
        }
        if (!empty($pairs = $this->getConflictPairs(array_keys($assignments)))) {
            $list = [];
            foreach ($pairs as $pair) {
                $list[] = $items[$pair[0]]->title . ' - <br/>' . $items[$pair[1]]->title;
            }
            $details['assignments_with_conflicts'] = [
                'status' => self::SATISFIED_CONFLICT,
                'text' => $this->getPlugin()->txt('assignments_with_conflicts'),
                'list' => $list
            ];
        }
        if (count($assignments) < $method->getNumberAssignments()
            && !($method instanceof ilCoSubMethodRandom && $method->allow_low_filled_users)) {
            $list = [
                sprintf($this->getPlugin()->txt('total_assignments_details'), $method->getNumberAssignments(), count($assignments))
            ];
            $details['total_assignments_not_reached'] = [
                'status' => self::SATISFIED_NOT,
                'text' => $this->getPlugin()->txt('total_assignments_not_reached'),
                'list' => $list
            ];
        }

        $not_chosen = [];
        $lower_priority = [];
        $highest_priority = [];
        foreach ($assignments as $item_id => $assign_id) {
            if (!isset($priorities[$item_id])) {
                $not_chosen[] = $this->items[$item_id]->title;         
            } 
            elseif ($priorities[$item_id] > 0) {
                $lower_priority[] = $this->items[$item_id]->title;
            }
            else {
                $highest_priority[] = $this->items[$item_id]->title;      
            }
        }
        if (!empty($not_chosen)) {
            $details['assignments_not_chosen'] = [
                'status' => self::SATISFIED_NOT,
                'text' => $this->getPlugin()->txt('assignments_not_chosen'),
                'list' => $not_chosen
            ];
        }
        if (!empty($lower_priority)) {
            $details['assignments_with_lower_priority'] = [
                'status' => self::SATISFIED_MEDIUM,
                'text' => $this->getPlugin()->txt('assignments_with_lower_priority'),
                'list' => $lower_priority
            ];
        }
        if (!empty($highest_priority)) {
            $details['assignments_with_highest_priority'] = [
                'status' => self::SATISFIED_FULL,
                'text' => $this->getPlugin()->txt('assignments_with_highest_priority'),
                'list' => $highest_priority
            ];
        }
        
        return $details;
    }

	/**
	 * Get the user ids of fixed users
	 * @return int[]
	 */
	public function getFixedUserIds(): array
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
	public function fixAssignedUsers(): void
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
	public function fixUsers(array $a_user_ids = []): void
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
	public function removeUserData(): void
	{
		ilCoSubAssign::_deleteForObject($this->getId());
		ilCoSubChoice::_deleteForObject($this->getId());
		ilCoSubRun::_deleteForObject($this->getId());
	}


	/**
	 * Get a list of reference ids that are due for an auto procesing
	 */
	public static function _getRefIdsForAutoProcess(): array
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
	public function handleAutoProcess(): bool
	{
		$this->setAutoProcess(false);
		$this->setLastProcess(new ilDateTime(time(), IL_CAL_UNIX));
		$this->update();

		// adjust subscribe users and maximum subscriptions
		$targets_obj = new ilCombiSubscriptionTargets($this, $this->getPlugin());
		$targets_obj->syncFromTargetsBeforeCalculation();


		// calculate the assignments
		$run = $this->getMethodObject()->getBestCalculationRun($this->getPlugin()->getNumberOfTries(), true);
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
		$conflictsObj = new ilCombiSubscriptionConflicts($this, $this->getPlugin());
		$removedConflicts = $conflictsObj->removeConflicts();

		// notify users about calculation result
		$notification = new ilCombiSubscriptionMailNotification();
		$notification->setPlugin($this->getPlugin());
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
	public function handleAutoProcessTargets(): void
	{
		$config = new ilCoSubTargetsConfig($this);
		$config->readFromObject();

		$targets_obj = new ilCombiSubscriptionTargets($this, $this->getPlugin());
		$targets_obj->filterUntrashedTargets();
		$targets_obj->applyTargetsConfig($config); 			// may change waiting list settings which is needed for assigning users
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
