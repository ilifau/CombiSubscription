<#1>
<?php
	$fields = array(
		'obj_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'is_online' => array(
			'type' => 'integer',
			'length' => 1,
			'notnull' => false
		),
		'explanation' => array(
			'type' => 'text',
			'length' => 2000,
			'notnull' => false
		),
		'sub_start' => array(
			'type' => 'timestamp',
			'notnull' => true
		),
		'sub_end' => array(
			'type' => 'timestamp',
			'notnull' => true
		),
		'method' => array(
			'type' => 'text',
			'length' => 50,
			'notnull' => true
		)
	);

	$ilDB->createTable('rep_robj_xcos_data', $fields);
	$ilDB->addPrimaryKey('rep_robj_xcos_data', array('obj_id'));
?>
<#2>
<?php
	$fields = array(
		'item_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'obj_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'target_ref_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => false
		),
		'title' => array(
			'type' => 'text',
			'length' => 255,
			'notnull' => true
		),
		'description' => array(
			'type' => 'text',
			'length' => 2000,
			'notnull' => false
		),
		'sub_min' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => false
		),
		'sub_max' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		)
	);

	$ilDB->createTable('rep_robj_xcos_items', $fields);
	$ilDB->addPrimaryKey('rep_robj_xcos_items', array('item_id'));
	$ilDB->addIndex('rep_robj_xcos_items', array('obj_id'), 'i1');
	$ilDB->addIndex('rep_robj_xcos_items', array('target_ref_id'),'i2');
	$ilDB->createSequence('rep_robj_xcos_items');
?>
<#3>
<?php
	$fields = array(
		'choice_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'obj_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'user_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'item_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'priority' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		)
	);
	$ilDB->createTable('rep_robj_xcos_choices', $fields);
	$ilDB->addPrimaryKey('rep_robj_xcos_choices', array('choice_id'));
	$ilDB->addIndex('rep_robj_xcos_choices', array('obj_id'), 'i1');
	$ilDB->addIndex('rep_robj_xcos_choices', array('user_id'),'i2');
	$ilDB->createSequence('rep_robj_xcos_choices');
?>
<#4>
<?php
	$fields = array(
		'run_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'obj_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'run_start' => array(
			'type' => 'timestamp',
			'notnull' => true
		),
		'run_end' => array(
			'type' => 'timestamp',
			'notnull' => false
		),
		'method' => array(
			'type' => 'text',
			'length' => 50,
			'notnull' => true
		),
		'details' => array(
			'type' => 'text',
			'length' => 2000,
			'notnull' => false
		)
	);
	$ilDB->createTable('rep_robj_xcos_runs', $fields);
	$ilDB->addPrimaryKey('rep_robj_xcos_runs', array('run_id'));
	$ilDB->addIndex('rep_robj_xcos_runs', array('obj_id'), 'i1');
	$ilDB->createSequence('rep_robj_xcos_runs');
?>
<#5>
	<?php
	$fields = array(
		'assign_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'obj_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'run_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => false
		),
		'user_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'item_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
	);
	$ilDB->createTable('rep_robj_xcos_ass', $fields);
	$ilDB->addPrimaryKey('rep_robj_xcos_ass', array('assign_id'));
	$ilDB->addIndex('rep_robj_xcos_ass', array('obj_id'), 'i1');
	$ilDB->addIndex('rep_robj_xcos_ass', array('run_id'), 'i2');
	$ilDB->createSequence('rep_robj_xcos_ass');
?>
<#6>
<?php
	$fields = array(
		'obj_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'class' => array(
			'type' => 'text',
			'length' => 50,
			'notnull' => true
		),
		'property' => array(
			'type' => 'text',
			'length' => 50,
			'notnull' => true
		),
		'value' => array(
			'type' => 'text',
			'length' => 250,
			'notnull' => false
		)
	);
	$ilDB->createTable('rep_robj_xcos_prop', $fields);
	$ilDB->addPrimaryKey('rep_robj_xcos_prop', array('obj_id','class','property'));
?>
<#7>
<?php
    if(!$ilDB->tableColumnExists('rep_robj_xcos_data', 'min_choices'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_data', 'min_choices', array(
                'type'    => 'integer',
                'length'  => 4,
                'notnull' => true,
                'default' => 0)
        );
    }
?>
<#8>
<?php
    if(!$ilDB->tableColumnExists('rep_robj_xcos_data', 'show_bars'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_data', 'show_bars', array(
                'type'    => 'integer',
                'length'  => 4,
                'notnull' => true,
                'default' => 1)
        );
    }
?>
<#9>
<?php
    if($ilDB->tableColumnExists('rep_robj_xcos_items', 'sub_max'))
    {
        $ilDB->modifyTableColumn('rep_robj_xcos_items', 'sub_max', array(
                'notnull' => false,
                'default' => null)
        );
    }
?>
<#10>
<?php
	if(!$ilDB->tableColumnExists('rep_robj_xcos_items', 'sort_position'))
	{
		$ilDB->addTableColumn('rep_robj_xcos_items', 'sort_position', array(
				'type'    => 'integer',
				'length'  => 4,
				'notnull' => false,
				'default' => null)
		);
	}
?>
<#11>
<?php
    if(!$ilDB->tableColumnExists('rep_robj_xcos_items', 'identifier'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_items', 'identifier', array(
                'type'    => 'text',
                'length'  => 50,
                'notnull' => false,
                'default' => null)
        );
    }
?>
<#12>
<?php
    $fields = array(
        'cat_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ),
        'obj_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ),
        'title' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true
        ),
        'description' => array(
            'type' => 'text',
            'length' => 2000,
            'notnull' => false
        ),
        'sort_position' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => false
        ),
        'min_choices' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => false
        ),
        'max_choices' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => false
        ),
		'assignments' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => false
		)
    );

    $ilDB->createTable('rep_robj_xcos_cats', $fields);
    $ilDB->addPrimaryKey('rep_robj_xcos_cats', array('cat_id'));
    $ilDB->addIndex('rep_robj_xcos_cats', array('obj_id'), 'i1');
    $ilDB->createSequence('rep_robj_xcos_cats');
?>
<#13>
<?php
    if(!$ilDB->tableColumnExists('rep_robj_xcos_items', 'cat_id'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_items', 'cat_id', array(
                'type'    => 'integer',
                'length'  => 4,
                'notnull' => false,
                'default' => null)
        );
    }
?>
<#14>
<?php
    if(!$ilDB->tableColumnExists('rep_robj_xcos_data', 'pre_select'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_data', 'pre_select', array(
                'type'    => 'integer',
                'length'  => 4,
                'notnull' => true,
                'default' => 0)
        );
    }
?>
<#15>
<?php
    if(!$ilDB->tableColumnExists('rep_robj_xcos_items', 'period_start'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_items', 'period_start', array(
				'type'    => 'integer',
				'length'  => 4,
				'notnull' => false,
				'default' => null)
        );
    }
    if(!$ilDB->tableColumnExists('rep_robj_xcos_items', 'period_end'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_items', 'period_end', array(
				'type'    => 'integer',
				'length'  => 4,
				'notnull' => false,
				'default' => null)
        );
    }
?>
<#16>
<?php
    if(!$ilDB->tableColumnExists('rep_robj_xcos_items', 'selectable'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_items', 'selectable', array(
                'type'    => 'integer',
                'length'  => 4,
                'notnull' => true,
                'default' => 1)
        );
    }
?>
<#17>
<?php
    $fields = array(
        'obj_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ),
		'user_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'is_fixed' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true,
            'default' => 0
		),
	);

    $ilDB->createTable('rep_robj_xcos_users', $fields);
    $ilDB->addPrimaryKey('rep_robj_xcos_users', array('obj_id', 'user_id'));
    $ilDB->addIndex('rep_robj_xcos_users', array('obj_id'), 'i1');
	$ilDB->addIndex('rep_robj_xcos_users', array('user_id'), 'i2');
?>
<#18>
<?php
    $query = "INSERT INTO rep_robj_xcos_users(obj_id, user_id, is_fixed) SELECT DISTINCT obj_id, user_id, 0 FROM rep_robj_xcos_choices";
    $ilDB->manipulate($query);
?>
<#19>
<?php
    $fields = array(
        'obj_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ),
        'schedule_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ),
		'item_id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		),
		'period_start' => array(
			'type'    => 'integer',
			'length'  => 4,
			'notnull' => true
        ),
		'period_end' => array(
			'type'    => 'integer',
			'length'  => 4,
			'notnull' => true
        ),
        'slots' => array (
            'type'  => 'text',
            'length'=> '4000',
            'notnull' => true,
        )
    );

    $ilDB->createTable('rep_robj_xcos_scheds', $fields);
    $ilDB->addPrimaryKey('rep_robj_xcos_scheds', array('schedule_id'));
    $ilDB->addIndex('rep_robj_xcos_scheds', array('obj_id'), 'i1');
    $ilDB->createSequence('rep_robj_xcos_scheds');
?>
<#20>
<?php
    if(!$ilDB->tableColumnExists('rep_robj_xcos_scheds', 'times'))
    {
        $ilDB->addTableColumn('rep_robj_xcos_scheds', 'times', array(
                'type'    => 'text',
                'length'  => 4000,
                'notnull' => true,
                'default' => 'a:0:{}')
        );
    }
?>