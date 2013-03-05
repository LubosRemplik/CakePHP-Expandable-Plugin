CakePHP Expandable Plugin
================================
Makes possible to save different fields for each item
in two tables without touching schema.

It works like Cake's i18n table

What it Does:
-------------------------------
The ExpandableBehavior will allow you to extend any model with any set of "extra fields" which don't exist in it's schema.

It uses a second table/model as a the key/value table, which links back to
the primary table/model.  Thus you can store any details you want separate
from the main table/model, keeping schema simpler and reducing (main) table size.

Setup:
-------------------------------

```
git clone git://github.com/LubosRemplik/CakePHP-Expandable-Plugin.git app/Plugin/Expandable
```

You must make a new Table to store the keys/values and optionally a Model for that table.
It should be named something like 'my_model_expands', and it needs the
following fields:

* a primary ID
* a foreignKey linking back to the primary table
* a "key" field
* a "value" field
** null if you want to get null back, varchar or text depending on what you want to store

In your schema:
-------------------------------

```
	public $my_model_expands = array(
		'id' => array('type' => 'string', 'null' => false, 'default' => null, 'length' => 36, 'key' => 'primary', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'my_model_id' => array('type' => 'integer', 'null' => false, 'default' => null, 'key' => 'index'),
		'created' => array('type' => 'datetime', 'null' => true, 'default' => null),
		'key' => array('type' => 'string', 'null' => false, 'default' => null, 'length' => 128, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'value' => array('type' => 'text', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1),
			'search' => array('column' => array('account_id', 'key'), 'unique' => 1)
		),
		'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB')
	);
```

And you can create the table via the shell:

```
./cake schema create
n
y
```

On MyModel:
-------------------------------

Setup the Expandable Behavior, configure "with" the Expand Model, and ensure
you put the Expand model into the "hasMany" array.

```
	public $actsAs = array(
		'Expandable.Expandable' => array(
			'with' => 'MyModelExpand',
		)
	);
	public $hasMany = array('MyModelExpand');
```

Usage:
-------------------------------

Just do normal `save()` on the model, and a normal `find()` (containing the
Expand data).

Your extra fields, not in the schema, will show up... add as many as you like.

For more information on this functionality, and a plain example of
functionality, check out the packaged unit tests

```
./cake test Expandable Model/Behavior/ExpandableBehavior
```

Credits:
-------------------------------

Primary source:

* @link http://debuggable.com/posts/unlimited-model-fields-expandable-behavior:48428c2e-9a88-47ec-ae8e-77a64834cda3
* @link https://github.com/felixge/debuggable-scraps/blob/master/cakephp/behaviors/expandable/expandable.php
* @link https://github.com/felixge/debuggable-scraps/blob/master/cakephp/behaviors/expandable/expandable.test.php

Repackaged:

* @link https://github.com/LubosRemplik/CakePHP-Expandable-Plugin

Updated:

* @link https://github.com/zeroasterisk/CakePHP-Expandable-Plugin

