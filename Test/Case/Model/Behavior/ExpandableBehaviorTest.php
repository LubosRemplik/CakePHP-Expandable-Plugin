<?php
/**
 *
 * Unit tests or ExpandableBehavior
 */
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
/**
 * User test Model class class
 */
class User extends AppModel {
	public $name = 'User';
	public $actsAs = array(
		'Expandable.Expandable' => array(
			'with' => 'UserExpand',
			'encode_json' => true,
			'encode_csv' => array('states'),
			'encode_date' => array(
				'birthdate' => 'Y-m-d',
				'birthday' => 'm/d',
			),
			'restricted_keys' => array('password', 'password_confirm'),
		),
		'Containable'
	);
	public $hasMany = array('Expandable.UserExpand');
	public $recursive = -1;
}
/**
 * ExpandableTest class
 *
 * @package       Cake.Test.Case.Model.Behavior
 */
class ExpandableBehaviorTest extends CakeTestCase {

	/**
	 * Fixtures associated with this test case
	 *
	 * @var array
	 */
	public $fixtures = array(
		'plugin.expandable.user',
		'plugin.expandable.user_expand',
	);

	/**
	 * Method executed before each test
	 *
	 */
	public function setUp() {
		parent::setUp();
		$this->User = ClassRegistry::init('Expandable.User');
		$this->User->Behaviors->attach('Expandable.Expandable');
	}

	/**
	 * Method executed after each test
	 *
	 */
	public function tearDown() {
		unset($this->User);
		parent::tearDown();
	}

	/**
	 * testContainments method
	 *
	 * @return void
	 */
	public function testAggregateFunctionality() {
		$user = $this->User->find('first');
		$userInit = $user;
		$user['User']['extraField1'] = 'extraValue1';
		$user['User']['extraField2'] = true;
		$user['User']['extraField3'] = false;
		$user['User']['extraField5'] = null;
		$user['User']['extraField6'] = '';
		// restricted
		$user['User']['password_confirm'] = 'Shouldnt be here';
		// date fields
		$user['User']['birthdate'] = array('year' => '1979', 'month' => '12', 'day' => '31');
		$user['User']['birthday'] = array('month' => '12', 'day' => '31');
		// CSV fields
		$user['User']['states'] = array('NY', 'CA', 'OH', 'KY', 'CT');
		// misc array
		$miscJsArray = array('one', 'two', 3, 4, true, false, null, '');
		$user['User']['miscJsArray'] = $miscJsArray;
		$miscJsObject = array('one' =>  'One', 'two' => 'Two', 3 => 3, 4 => 4, 'true' => true, 'false' => false, 'null' => null, '');
		$user['User']['miscJsObject'] = $miscJsObject;

		$this->User->create(false);
		$saved = $this->User->save($user);
		$this->assertFalse(empty($saved));
		// now if we find that record again, it wont have the expands
		// because recursive = -1, and no contains
		$userWithoutExpand = $this->User->find('first');
		$this->assertEqual($userInit, $userWithoutExpand);
		// but if we repeat the find with a contains (or recursive = 1)
		$userWithExpand = $this->User->find('first', array('contain' => 'UserExpand'));
		$this->assertNotEqual($userInit, $userWithExpand);
		// now we can test the values directly on the User model results
		$this->assertEquals('extraValue1', $userWithExpand['User']['extraField1']);
		$this->assertEquals(true, $userWithExpand['User']['extraField2']);
		$this->assertEquals(false, $userWithExpand['User']['extraField3']);
		$this->assertEquals(null, $userWithExpand['User']['extraField5']);
		$this->assertEquals('', $userWithExpand['User']['extraField6']);
		// restricted
		$this->assertTrue(empty($userWithExpand['User']['password_confirm']));
		// date fields
		$this->assertEquals('1979-12-31', $userWithExpand['User']['birthdate']);
		$this->assertEquals('12/31', $userWithExpand['User']['birthday']);
		// CSV fields
		$this->assertEquals('NY,CA,OH,KY,CT', $userWithExpand['User']['states']);
		// misc array
		$this->assertEquals($miscJsArray, $userWithExpand['User']['miscJsArray']);
		$this->assertEquals($miscJsObject, $userWithExpand['User']['miscJsObject']);
		// the hasMany relationship is passed through... but we are removing
		// for simpler full-array tesing
		$this->assertFalse(empty($userWithExpand['UserExpand']));
		unset($userWithExpand['UserExpand']);
		// convert a few transformed values
		$user['User']['extraField2'] = 1; // transformed on save?
		$user['User']['birthdate'] = '1979-12-31';
		$user['User']['birthday'] = '12/31';
		$user['User']['states'] = implode(',', $user['User']['states']);
		unset($user['User']['password_confirm']);
		$this->assertEqual($user, $userWithExpand);
	}
}
