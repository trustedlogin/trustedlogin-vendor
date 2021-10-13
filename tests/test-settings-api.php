<?php

use TrustedLogin\Vendor\TeamSettings;

/**
 * Tests PHP Settings API
 */
class SettingsApiTest extends WP_UnitTestCase {

	/**
	 * @covers TeamSettings::reset()
	 * @covers TeamSettings::to_array()
	 */
	public function test_settings_object_defaults(){
		$data = [
			'account_id'       => '6',
			'private_key'      => '7',
			'api_key'       	=> '8',
		];
		$setting = new TeamSettings(
			$data
		);
		//Do defaults get overridden when possible?
		$this->assertSame(
			$data['api_key'],
			$setting->to_array()['api_key']
		);
		//Do default values get set when needed?
		$this->assertSame(
			[ 'helpscout' ],
			$setting->to_array()['helpdesk']
		);
	}

	/**
	 * @covers TeamSettings::reset()
	 * @covers TeamSettings::to_array()
	 */
	public function test_settings_object_reset(){

		$setting = new TeamSettings(
			[
				'account_id'       => '16',
				'private_key'      => '17',
				'api_key'       	=> '18',
			]
		);
		$data = [
			'account_id'       => '6',
			'private_key'      => '7',
			'api_key'       	=> '8',
		];
		$setting = $setting->reset($data);
		//Do defaults get overridden when possible?
		$this->assertSame(
			$data['api_key'],
			$setting->to_array()['api_key']
		);
		//Do default values get set when needed?
		$this->assertSame(
			[ 'helpscout' ],
			$setting->to_array()['helpdesk']
		);
	}

	/**
	 * @covers TeamSettings::get()
	 * @covers TeamSettings::set()
	 */
	public function test_settings_object_get_set(){
		$data = [
			'account_id'       => '6',
			'private_key'      => '7',
			'api_key'       	=> '8',
		];
		$setting = new TeamSettings(
			$data
		);

		$setting = $setting->set('account_id', '42');
		$this->assertSame(
			'42',
			 $setting->get('account_id')
		);
		$this->assertSame(
			'42',
			$setting->to_array()['account_id']
		);
	}

	/**
	 * @covers TeamSettings::valid()
	 */
	public function test_settings_object_set_invalid(){
		$data = [
			'account_id'       => '6',
			'private_key'      => '7',
			'api_key'       	=> '8',
		];
		$setting = new TeamSettings(
			$data
		);

		$this->expectException( \Exception::class);
		$setting = $setting->set('droids', 'not the ones you are looking for');

	}

	/**
	 * @covers TeamSettings::valid()
	 */
	public function test_settings_object_get_invalid(){
		$data = [
			'account_id'       => '6',
			'private_key'      => '7',
			'api_key'       	=> '8',
		];
		$setting = new TeamSettings(
			$data
		);

		$this->expectException( \Exception::class);
		$setting = $setting->get('droids', 'not the ones you are looking for');

	}
}
