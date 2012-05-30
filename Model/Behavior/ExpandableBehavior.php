<?php
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
			foreach ($results as $i => $item) {
				foreach ($item[$with] as $field) {
					$value = $field['value'];
					$valueArray = @unserialize($field['value']);
					if ($valueArray !== false) {
						$value = $valueArray;
					}
					$results[$i][$model->alias][$field['key']] = $value;
				}
			}
		}
		return $results;
	}
	
	public function beforeSave(&$model) {

		$settings = $this->settings[$model->alias];
		$this->_fieldsToSave = array_diff_key($model->data[$model->alias], $settings['schema']);
		foreach ($this->_fieldsToSave as $key => $value) {
			if (is_array($value)) {
				$this->_fieldsToSave[$key] = serialize($value);
			}
		}
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
