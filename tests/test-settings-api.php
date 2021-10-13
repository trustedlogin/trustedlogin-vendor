<?php

use TrustedLogin\Vendor\SettingsApi;
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

	/**
	 * @covers SettingsApi::get_by_account_id()
	 * @covers TeamSettings::get()
	 */
	public function test_settings_collection_get(){
		$data = [
			[
				'account_id'       => '16',
				'private_key'      => '17',
				'api_key'       	=> '18',
			],
			[
				'account_id'       => '26',
				'private_key'      => '27',
				'api_key'       	=> '28',
			]
		];
		$settings = new SettingsApi($data);
		$this->assertSame(
			'27',
			$settings->get_by_account_id('26')
				->get( 'private_key')
		);
	}

	/**
	 *
	 * @covers SettingsApi::get_by_account_id()
	 * @covers SettingsApi::update_by_account_id()
	 * @covers TeamSettings::get()
	 * @covers TeamSettings::set()
	 */
	public function test_settings_collection_update(){
		$data = [
			[
				'account_id'       => '216',
				'private_key'      => '217',
				'api_key'       	=> '218',
			],
			[
				'account_id'       => '26',
				'private_key'      => '227',
				'api_key'       	=> '228',
			]
		];
		$settings = new SettingsApi($data);

		$this->assertTrue(
			is_object($settings->get_by_account_id('26'))
		);
		$settings = $settings->update_by_account_id(
			$settings->get_by_account_id('26')
				->set( 'private_key', 'pkforks' )
		);
		$this->assertSame(
			'pkforks',
			$settings->get_by_account_id('26')
				->get( 'private_key')
		);
	}

	/**
	 *
	 * @covers SettingsApi::get_by_account_id()
	 */
	public function test_settings_collection_get_invalid(){
		$data = [
			[
				'account_id'       => '216',
				'private_key'      => '217',
				'api_key'       	=> '218',
			],
			[
				'account_id'       => '26',
				'private_key'      => '227',
				'api_key'       	=> '228',
			]
		];
		$settings = new SettingsApi($data);
		$this->expectException(\Exception::class);
		$settings->get_by_account_id('aaa26');

	}


	/**
	 *
	 * @covers SettingsApi::from_saved()
	 * @covers SettingsApi::save()
	 * @covers SettingsApi::update_by_account_id()
	 * @covers TeamSettings::get()
	 */
	public function test_settings_save(){
		update_option( SettingsApi::SETTING_NAME, false );
		$data = [
			[
				'account_id'       => 'a216',
				'private_key'      => 'a217',
				'api_key'       	=> 'a218',
			],
			[
				'account_id'       => 'b26',
				'private_key'      => 'b227',
				'api_key'       	=> 'b228',
			]
		];
		$settings = new SettingsApi($data);

		$settings->save();

		$settings = SettingsApi::from_saved();

		$this->assertSame(
			'b227',
			$settings->get_by_account_id('b26')
				->get( 'private_key')
		);

	}


}
