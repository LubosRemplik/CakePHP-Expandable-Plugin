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

	public $defaults = array(
		// if a value is an array or object we can encode/decode via: json
		'encode_json' => true,
		// Ignore all of these fields (never save them) security like whoa!
		'restricted_keys' => array(),
		// CSV strings are awesome -- they let us look FIND_IN_SET() in mysql
		//   if you don't need that, no need for CSV, Expandable will auto-encode/decode JSON
		//   NOTE: don't send indexed arrays, as the keys will be lost
		'encode_csv' => array(),
		// Date inputs from CakePHP can come in as arrays, this is the handler:
		//   'birthdate' => 'Y-m-d',
		//   'card_expires' => 'm/y',
		'encode_date' => array(),
   	);

	public $settings = array();

	private $_fieldsToSave = array();

	/**
	 * Setup the model
	 *
	 * @param object Model $Model
	 * @param array $settings
	 * @return boolean
	 */
	public function setup(Model $Model, $settings = array()) {
		if (isset($settings['with'])) {
			$base = array('schema' => $Model->schema());
			$settings = array_merge($settings, $base);
			$settings = array_merge($this->defaults, $settings);
			$settings = Set::normalize($settings);
			return $this->settings[$Model->alias] = $settings;
		}
	}

	/**
	 * Standard afterFind() callback
	 * Inject the expandable data (as fields)
	 *
	 * @param object Model $Model
	 * @param mixed $results
	 * @param boolean $primary
	 * @return mixed $results
	 */
	public function afterFind(Model $Model, $results, $primary = false) {
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (!empty($settings['with'])) {
			$with = $settings['with'];
			if (!Set::matches('/' . $with, $results)) {
				return;
			}
			foreach (array_keys($results) as $i) {
				foreach (array_keys($results[$i][$with]) as $j) {
					$key = $results[$i][$with][$j]['key'];
					$value = $results[$i][$with][$j]['value'];
					$results[$i][$Model->alias][$key] = $this->decode($Model, $value);
				}
			}
		}
		return $results;
	}

	/**
	 * Standard beforeSave() callback
	 * Sets up what data will be saved for expandable
	 *
	 * @param object Model $Model
	 * @return boolean
	 */
	public function beforeSave(Model $Model, $options = array()) {
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (isset($settings['schema'])) {
			$this->_fieldsToSave = array_diff_key($Model->data[$Model->alias], $settings['schema']);
		}
		return true;
	}
	
	/**
	 * Standard afterSave() callback
	 * Actually save the expandable data (one record for each fieldsToSave)
	 *
	 * @param object Model $Model
	 * @param boolean $created
	 * @return boolean
	 */
	public function afterSave(Model $Model, $created, $options = array()) {
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (!empty($settings['with']) && !empty($this->_fieldsToSave)) {
			$with = $settings['with'];
			$assoc = $Model->hasMany[$with];
			$foreignKey = $assoc['foreignKey'];
			$id = $Model->id;
			// set of "keys" we will ignore
			$restricted_keys = $settings['restricted_keys'];
			// automatically ignore all associated models
			$restricted_keys = array_merge($restricted_keys, array_keys($Model->belongsTo));
			$restricted_keys = array_merge($restricted_keys, array_keys($Model->hasOne));
			$restricted_keys = array_merge($restricted_keys, array_keys($Model->hasMany));
			$restricted_keys = array_merge($restricted_keys, array_keys($Model->hasAndBelongsToMany));
			foreach ($this->_fieldsToSave as $key => $val) {
				if (in_array($key, $restricted_keys, true)) {
					continue;
				}
				$fieldId = $Model->{$with}->field('id', array(
					$with . '.' . $foreignKey => $id,
					$with . '.key' => $key
				));
				$data = array('value' => $this->encode($Model, $val, $key));
				if (!empty($fieldId)) {
					$Model->{$with}->id = $fieldId;
				} else {
					$Model->{$with}->create();
				}
				$data[$foreignKey] = $id;
				$data['key'] = $key;
				$saved = $Model->{$with}->save($data);
			}
			return true;
		}
	}

	/**
	 * Optionally encode various inputs into a normalized storage string
	 *   see $defaults to see what settings are possible
	 *
	 * @param mixed $value
	 * @return string $value
	 */
	private function encode(Model $Model, $value, $key) {
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (!empty($settings['encode_date'])) {
			$value = $this->encode_date($Model, $value, $key);
		}
		if (!empty($settings['encode_csv'])) {
			$value = $this->encode_csv($Model, $value, $key);
		}
		if (!empty($settings['encode_json'])) {
			$value = $this->encode_json($Model, $value, $key);
		}
		return $value;
	}

	/**
	 * Encode dates which may be passed in as an array
	 *
	 * @param Model $Model
	 * @param mixed $value
	 * @param string $key
	 * @return string $value
	 */
	private function encode_date(Model $Model, $value, $key) {
		if (!is_array($value)) {
			return $value;
		}
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (empty($settings['encode_date'][$key])) {
			return $value;
		}
		$format = $settings['encode_date'][$key];
		if (!is_string($format)) {
			$format = 'Y-m-d';
		}
		// parses inputs generated by CakePHP date helpers
		$dateField = Set::filter(array_merge(array('year' => date('Y'), 'month' => date('m'), 'day' => date('d')), $value));
		$datestring = $dateField['year'] . '-' .$dateField['month'] . '-' . $dateField['day'];
		$dateObject = DateTime::createFromFormat('Y-m-d', $datestring);
		return $dateObject->format($format);
	}

	/**
	 * Encode fields which may be passed in as an array, as a CSV string
	 * (for use with FIND_IN_SET() searching in MySQL)
	 *
	 * @param Model $Model
	 * @param mixed $value
	 * @param string $key
	 * @return string $value
	 */
	private function encode_csv(Model $Model, $value, $key) {
		if (!is_array($value)) {
			return $value;
		}
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (empty($settings['encode_csv'][$key]) && !in_array($key, $settings['encode_csv'], true)) {
			return $value;
		}
		array_walk($value, create_function('&$val', '$val = trim(strval($val));'));
		return implode(',', $value);
	}

	/**
	 * Optionally encode non-string/numeric inputs into JSON strings
	 *
	 * @param Model $Model
	 * @param mixed $value
	 * @param string $key
	 * @return string $value
	 */
	private function encode_json(Model $Model, $value, $key) {
		if ($value === true) {
			return 'true';
		}
		if ($value === false) {
			return 'false';
		}
		if ($value === null) {
			return 'null';
		}
		if (is_string($value) || is_numeric($value)) {
			return $value;
		}
		return json_encode($value);
	}

	/**
	 * Optionally decode JSON strings into true/expanded values
	 *
	 * @param string $value
	 * @return mixed $value
	 */
	private function decode(Model $Model, $value) {
		if (empty($value)) {
			return $value;
		}
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (!$settings['encode_json']) {
			return $value;
		}
		if ($value == 'true') {
			return true;
		}
		if ($value == 'false') {
			return false;
		}
		if ($value == 'null') {
			return null;
		}
		# Work around for php behavior:  json_decode("01234") -> 1234.  Losing the leading zero value even if it's a string.
		if (is_string($value) && preg_match('/^0\d+$/', $value)) {
			return $value;
		}
		$decoded = @json_decode($value, true);
		if ($decoded != null) {
			return $decoded;
		}
		return $value;
	}
}
