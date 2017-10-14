<?php

/**
 * Category of a combined subscription
 */
class ilCoSubCategory
{
	/** @var  integer */
	public $cat_id;

	/** @var  integer */
	public $obj_id;

	/** @var  string */
	public $title;

	/** @var  string */
	public $description;

	/** @var  integer */
	public $sort_position;

	/** @var  integer|null */
	public $min_choices;

	/** @var  integer|null */
	public $max_assignments;


	/**
	 * Get category by id
	 * @param integer  $a_id
	 * @return ilCoSubCategory or null if not exists
	 */
	public static function _getById($a_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_cats'
			.' WHERE cat_id = '. $ilDB->quote($a_id,'integer');

		$res = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubCategory;
			$obj->fillData($row);
			return $obj;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Delete a category by its id
	 * @param integer $a_id
	 */
	public static function _deleteById($a_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_cats WHERE cat_id = ' . $ilDB->quote($a_id,'integer'));
		$ilDB->manipulate('UPDATE rep_robj_xcos_items SET cat_id = NULL WHERE cat_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Get categories by parent object id
	 * @param integer   object id
	 * @return ilCoSubCategory[]	indexed by cat_id
	 */
	public static function _getForObject($a_obj_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_cats'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
			.' ORDER BY sort_position ASC';

		$objects = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubCategory;
			$obj->fillData($row);
			$objects[$obj->cat_id] = $obj;
		}
		return $objects;
	}

	/**
	 * Delete all categories for a parent object id
	 * @param integer object id
	 */
	public static function _deleteForObject($a_obj_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_cats WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer'));
	}

	/**
	 * Clone the item for a new object
	 * @param int	$a_obj_id
	 * @return self
	 */
	public function saveClone($a_obj_id)
	{
		$clone = clone $this;
		$clone->obj_id = $a_obj_id;
		$clone->cat_id = null;
		$clone->save();
		return $clone;
	}


	/**
	 * Fill the properties with data from an array
	 * @param array $data assoc data
	 */
	protected function fillData($data)
	{
		$this->cat_id = $data['cat_id'];
		$this->obj_id = $data['obj_id'];
		$this->title = $data['title'];
		$this->description = $data['description'];
		$this->sort_position = $data['sort_position'];
		$this->min_choices = $data['min_choices'];
		$this->max_assignments = $data['assignments'];
	}

	/**
	 * Save an item object
	 * @return  boolean     success
	 */
	public function save()
	{
		global $ilDB;

		if (empty($this->obj_id) || empty($this->title))
		{
			return false;
		}
		if (empty($this->cat_id))
		{
			$this->cat_id = $ilDB->nextId('rep_robj_xcos_cats');
		}
		if (!isset($this->sort_position))
		{
			$query = "SELECT MAX(sort_position) pos FROM rep_robj_xcos_cats WHERE obj_id= ". $ilDB->quote($this->obj_id,'integer');
			$res = $ilDB->query($query);
			$row = $ilDB->fetchAssoc($res);
			$this->sort_position = (int) $row['pos'] + 1;
		}
		$rows = $ilDB->replace('rep_robj_xcos_cats',
			array(
				'cat_id' => array('integer', $this->cat_id)
			),
			array(
				'obj_id' => array('integer', $this->obj_id),
				'title' => array('text', $this->title),
				'description' => array('text', $this->description),
				'sort_position' => array('integer', $this->sort_position),
				'min_choices' => array('integer', $this->min_choices),
				'assignments' => array('integer', $this->max_assignments),
			)
		);
		return $rows > 0;
	}
}