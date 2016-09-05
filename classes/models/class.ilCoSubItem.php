<?php

/**
 * Item of a combined subscription
 */
class ilCoSubItem
{
	/** @var  integer */
	public $item_id;

	/** @var  integer */
	public $obj_id;

	/** @var  integer */
	public $target_ref_id;

	/** @var  string */
	public $title;

	/** @var  string */
	public $description;

	/** @var  integer */
	public $sort_position;

	/** @var  integer */
	public $sub_min;

	/** @var  integer */
	public $sub_max;


	/**
	 * Get item by id
	 * @param integer  item id
	 * @return ilCoSubItem or null if not exists
	 */
	public static function _getById($a_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_items'
			.' WHERE item_id = '. $ilDB->quote($a_id,'integer');

		$res = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubItem;
			$obj->fillData($row);
			return $obj;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Delete an item by its id
	 * @param integer item id
	 */
	public static function _deleteById($a_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_items WHERE item_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Get items by parent object id
	 * @param integer   object id
	 * @return ilCoSubItem[]
	 */
	public static function _getForObject($a_obj_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_items'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
			.' ORDER BY sort_position ASC';

		$objects = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubItem;
			$obj->fillData($row);
			$objects[] = $obj;
		}
		return $objects;
	}

	/**
	 * Delete all items for a parent object id
	 * @param integer object id
	 */
	public static function _deleteForObject($a_obj_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_items WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer'));
	}

	/**
	 * Fill the properties with data from an array
	 * @param array assoc data
	 */
	protected function fillData($data)
	{
		$this->item_id = $data['item_id'];
		$this->obj_id = $data['obj_id'];
		$this->target_ref_id = $data['target_ref_id'];
		$this->title = $data['title'];
		$this->description = $data['description'];
		$this->sort_position = $data['sort_position'];
		$this->sub_min = $data['sub_min'];
		$this->sub_max = $data['sub_max'];
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
		if (empty($this->item_id))
		{
			$this->item_id = $ilDB->nextId('rep_robj_xcos_items');
		}
		$rows = $ilDB->replace('rep_robj_xcos_items',
			array(
				'item_id' => array('integer', $this->item_id)
			),
			array(
				'obj_id' => array('integer', $this->obj_id),
				'target_ref_id' => array('integer', $this->target_ref_id),
				'title' => array('text', $this->title),
				'description' => array('text', $this->description),
				'sort_position' => array('integer', $this->sort_position),
				'sub_min' => array('integer', $this->sub_min),
				'sub_max' => array('integer', $this->sub_max)
			)
		);
		return $rows > 0;
	}
}