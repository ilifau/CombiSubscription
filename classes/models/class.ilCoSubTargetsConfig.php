<?php

/**
 * Class ilCoSubTargetsConfig
 */
class ilCoSubTargetsConfig
{
	const SUB_TYPE_COMBI = 'combi';
	const SUB_TYPE_DIRECT = 'direct';
	const SUB_TYPE_CONFIRM = 'confirm';
	const SUB_TYPE_NONE = 'none';

	const SUB_WAIT_MANU = 'manu';
	const SUB_WAIT_AUTO = 'auto';
	const SUB_WAIT_NONE = 'none';

	const SET_BY_ITEM = 'item';
	const SET_BY_INPUT = 'input';


	/** @var ilObjCombiSubscription */
	protected $object;

	public $set_sub_type = false;
	public $set_sub_period = false;
	public $set_sub_min = false;
	public $set_sub_max = false;
	public $set_sub_wait = false;

	public $sub_type;
	public $sub_period_start;
	public $sub_period_end;
	public $sub_wait;

	public $sub_min_by = self::SET_BY_ITEM;
    public $sub_max_by = self::SET_BY_ITEM;
    public $sub_min;
    public $sub_max;


    /** @var bool send the standard emails for user assignments in groups etc */
	public $send_target_emails = false;


	/**
	 * ilCoSubTargetsConfig constructor.
	 * @param $object
	 */
	public function __construct($object)
	{
		$this->object = $object;
	}

	/**
	 * Save config in user session (for manual configuration of target objects)
	 */
	public function saveInSession()
	{
		$this->object->setPreference('ilCoSubTargetsConfig', 'set_sub_type', $this->set_sub_type);
		$this->object->setPreference('ilCoSubTargetsConfig', 'set_sub_period', $this->set_sub_period);
		$this->object->setPreference('ilCoSubTargetsConfig', 'set_sub_min', $this->set_sub_min);
		$this->object->setPreference('ilCoSubTargetsConfig', 'set_sub_max', $this->set_sub_max);
		$this->object->setPreference('ilCoSubTargetsConfig', 'set_sub_wait', $this->set_sub_wait);
		$this->object->setPreference('ilCoSubTargetsConfig', 'sub_type', $this->sub_type);
		$this->object->setPreference('ilCoSubTargetsConfig', 'sub_period_start', $this->sub_period_start);
		$this->object->setPreference('ilCoSubTargetsConfig', 'sub_period_end', $this->sub_period_end);
		$this->object->setPreference('ilCoSubTargetsConfig', 'sub_wait', $this->sub_wait);
        $this->object->setPreference('ilCoSubTargetsConfig', 'sub_min_by', $this->sub_min_by);
		$this->object->setPreference('ilCoSubTargetsConfig', 'sub_max_by', $this->sub_max_by);
        $this->object->setPreference('ilCoSubTargetsConfig', 'sub_min', $this->sub_min);
        $this->object->setPreference('ilCoSubTargetsConfig', 'sub_max', $this->sub_max);
    }

	/**
	 * Read config from user session (for manual configuration of target objects)
	 */
	public function readFromSession()
	{
		$this->set_sub_type = (bool) $this->object->getPreference('ilCoSubTargetsConfig', 'set_sub_type', false);
		$this->set_sub_period = (bool) $this->object->getPreference('ilCoSubTargetsConfig', 'set_sub_period', false);
		$this->set_sub_min = (bool) $this->object->getPreference('ilCoSubTargetsConfig', 'set_sub_min', false);
		$this->set_sub_max = (bool) $this->object->getPreference('ilCoSubTargetsConfig', 'set_sub_max', false);
		$this->set_sub_wait = (bool) $this->object->getPreference('ilCoSubTargetsConfig', 'set_sub_wait', false);

		$this->sub_type = (string) $this->object->getPreference('ilCoSubTargetsConfig', 'sub_type', self::SUB_TYPE_COMBI);
		$this->sub_period_start = (int) $this->object->getPreference('ilCoSubTargetsConfig', 'sub_period_start', $this->object->getSubscriptionStart()->get(IL_CAL_UNIX));
		$this->sub_period_end = (int) $this->object->getPreference('ilCoSubTargetsConfig', 'sub_period_end', $this->object->getSubscriptionEnd()->get(IL_CAL_UNIX));
		$this->sub_wait = (string) $this->object->getPreference('ilCoSubTargetsConfig', 'sub_wait', self::SUB_WAIT_AUTO);

        $this->sub_min_by = (string) $this->object->getPreference('ilCoSubTargetsConfig', 'sub_min_by', self::SET_BY_ITEM);
        $this->sub_max_by = (string) $this->object->getPreference('ilCoSubTargetsConfig', 'sub_max_by', self::SET_BY_ITEM);
        $this->sub_min = (int) $this->object->getPreference('ilCoSubTargetsConfig', 'sub_min', 0);
        $this->sub_max = (int) $this->object->getPreference('ilCoSubTargetsConfig', 'sub_max', 100);
	}

	/**
	 * Save config for object (for auto assignment)
	 */
	public function saveInObject()
	{
		$this->object->setClassProperty('ilCoSubTargetsConfig', 'set_sub_type', $this->set_sub_type);
		$this->object->setClassProperty('ilCoSubTargetsConfig', 'set_sub_period', $this->set_sub_period);
		$this->object->setClassProperty('ilCoSubTargetsConfig', 'set_sub_min', $this->set_sub_min);
		$this->object->setClassProperty('ilCoSubTargetsConfig', 'set_sub_max', $this->set_sub_max);
		$this->object->setClassProperty('ilCoSubTargetsConfig', 'set_sub_wait', $this->set_sub_wait);

		$this->object->setClassProperty('ilCoSubTargetsConfig', 'sub_type', $this->sub_type);
		$this->object->setClassProperty('ilCoSubTargetsConfig', 'sub_period_start', $this->sub_period_start);
		$this->object->setClassProperty('ilCoSubTargetsConfig', 'sub_period_end', $this->sub_period_end);
		$this->object->setClassProperty('ilCoSubTargetsConfig', 'sub_wait', $this->sub_wait);

        $this->object->setClassProperty('ilCoSubTargetsConfig', 'sub_min_by', $this->sub_min_by);
        $this->object->setClassProperty('ilCoSubTargetsConfig', 'sub_max_by', $this->sub_max_by);
        $this->object->setClassProperty('ilCoSubTargetsConfig', 'sub_min', $this->sub_min);
        $this->object->setClassProperty('ilCoSubTargetsConfig', 'sub_max', $this->sub_max);

        $this->object->setClassProperty('ilCoSubTargetsConfig', 'send_target_emails', $this->send_target_emails);
	}

	/**
	 * Read config from object (for auto assignment)
	 */
	public function readFromObject()
	{
		$this->set_sub_type = (bool) $this->object->getClassProperty('ilCoSubTargetsConfig', 'set_sub_type', false);
		$this->set_sub_period = (bool) $this->object->getClassProperty('ilCoSubTargetsConfig', 'set_sub_period', false);
		$this->set_sub_min = (bool) $this->object->getClassProperty('ilCoSubTargetsConfig', 'set_sub_min', false);
		$this->set_sub_max = (bool) $this->object->getClassProperty('ilCoSubTargetsConfig', 'set_sub_max', false);
		$this->set_sub_wait = (bool) $this->object->getClassProperty('ilCoSubTargetsConfig', 'set_sub_wait', false);

		$this->sub_type = (string) $this->object->getClassProperty('ilCoSubTargetsConfig', 'sub_type', self::SUB_TYPE_COMBI);
		$this->sub_period_start = (int) $this->object->getClassProperty('ilCoSubTargetsConfig', 'sub_period_start', $this->object->getSubscriptionStart()->get(IL_CAL_UNIX));
		$this->sub_period_end = (int) $this->object->getClassProperty('ilCoSubTargetsConfig', 'sub_period_end', $this->object->getSubscriptionEnd()->get(IL_CAL_UNIX));
		$this->sub_wait = (string) $this->object->getClassProperty('ilCoSubTargetsConfig', 'sub_wait', self::SUB_WAIT_AUTO);

        $this->sub_min_by = (string) $this->object->getClassProperty('ilCoSubTargetsConfig', 'sub_min_by', self::SET_BY_ITEM);
        $this->sub_max_by = (string) $this->object->getClassProperty('ilCoSubTargetsConfig', 'sub_max_by', self::SET_BY_ITEM);
        $this->sub_min = (int) $this->object->getClassProperty('ilCoSubTargetsConfig', 'sub_min', 0);
        $this->sub_max = (int) $this->object->getClassProperty('ilCoSubTargetsConfig', 'sub_max', 100);

        $this->send_target_emails = (bool) $this->object->getClassProperty('ilCoSubTargetsConfig', 'send_target_emails', true);
	}
}