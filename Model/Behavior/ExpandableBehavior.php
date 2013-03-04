<?php
/**
 * ExpandableBehavior will allow you to extend any model with any set of fields
 *
 * It uses a second table/model as a the key/value table, which links back to
 * the primary table/model.  Thus you can store any details you want separate
 * from the main table/model, keeping schema simpler and reducing table size.
 *
 * Usage:
 *   You must make a new table and optionally a Model for that table
 *     It should be named <my_model>_expands (or really anything you like)
 *     It needs to have a primary ID,
 *       a foreignKey linking back to the primary table,
 *       and it must have a "key" field and a "value" field
 *
 *   Then just link in the Behavior and all your saves and finds will
 *     auto-extend with the extra fields/values
 *
 * In your schema:
 *
	public $user_expands = array(
		'id' => array('type' => 'string', 'null' => false, 'default' => null, 'length' => 36, 'key' => 'primary', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'user_id' => array('type' => 'string', 'null' => false, 'default' => null, 'length' => 36, 'key' => 'index', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'created' => array('type' => 'datetime', 'null' => true, 'default' => null),
		'key' => array('type' => 'string', 'null' => true, 'default' => null, 'length' => 128, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'value' => array('type' => 'text', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1),
			'search' => array('column' => array('user_id', 'key'), 'unique' => 1)
		),
		'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB')
	);
 *	);
 *
 *
 * On MyModel:
 *
class User extends AppModel {
	public $name = 'User';
	public $actsAs = array('Expandable.Expandable' => array('with' => 'UserExpand'), 'Containable');
	public $hasMany = array('Expandable.UserExpand');
}
 *
 *
 * For more information on this functionality, and a plain example of
 * functionality, check out the packaged unit tests
 *
 * Primary source:
 * @link http://debuggable.com/posts/unlimited-model-fields-expandable-behavior:48428c2e-9a88-47ec-ae8e-77a64834cda3
 * @link https://github.com/felixge/debuggable-scraps/blob/master/cakephp/behaviors/expandable/expandable.php
 * @link https://github.com/felixge/debuggable-scraps/blob/master/cakephp/behaviors/expandable/expandable.test.php
 *
 * Repackaged:
 * @link https://github.com/LubosRemplik/CakePHP-Expandable-Plugin
 *
 * Updated:
 * @link https://github.com/zeroasterisk/CakePHP-Expandable-Plugin
 *
 */
class ExpandableBehavior extends ModelBehavior {

	public $settings = array();

	private $_fieldsToSave = array();

	public function setup(&$model, $settings = array()) {
		if (isset($settings['with'])) {
			$base = array('schema' => $model->schema());
			$settings = array_merge($settings, $base);
			return $this->settings[$model->alias] = $settings;
		}
	}

	public function afterFind(&$model, $results, $primary) {
		$settings = $this->settings[$model->alias];
		if (!empty($settings['with'])) {
			$with = $settings['with'];
			if (!Set::matches('/' . $with, $results)) {
				return;
			}
			foreach (array_keys($results) as $i) {
				foreach (array_keys($results[$i][$with]) as $j) {
					$key = $results[$i][$with][$j]['key'];
					$value = $results[$i][$with][$j]['value'];
					$results[$i][$model->alias][$key] = $value;
				}
			}
		}
		return $results;
	}

	public function beforeSave(&$model) {
		$settings = $this->settings[$model->alias];
		$this->_fieldsToSave = array_diff_key($model->data[$model->alias], $settings['schema']);
		return true;
	}

	public function afterSave(&$model) {
		$settings = $this->settings[$model->alias];
		if (!empty($settings['with']) && !empty($this->_fieldsToSave)) {
			$with = $settings['with'];
			$assoc = $model->hasMany[$with];
			$foreignKey = $assoc['foreignKey'];
			$id = $model->id;
			foreach ($this->_fieldsToSave as $key => $val) {
				$fieldId = $model->{$with}->field('id', array(
					$with . '.' . $foreignKey => $id,
					$with . '.key' => $key
				));
				$data = array('value' => $val);
				if (!empty($fieldId)) {
					$model->{$with}->id = $fieldId;
				} else {
					$model->{$with}->create();
					$data[$foreignKey] = $id;
					$data['key'] = $key;
				}
				$saved = $model->{$with}->save($data);
			}
			return true;
		}
	}

}
