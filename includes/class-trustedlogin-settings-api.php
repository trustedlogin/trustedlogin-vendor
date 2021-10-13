<?php
/**
 * Class: TrustedLogin Settings API
 *
 * @package trustedlogin-vendor
 * @version 0.10.0
 */

namespace TrustedLogin\Vendor;

use \WP_Error;
use \Exception;


class SettingsApi {

	const SETTING_NAME = 'trustedlogin_vendor_team_settings';

	/**
	 * @var TeamSettings[]
	 * @since 0.10.0
	 */
	protected $settings = [];


	/**
	 * @param TeamSettings[]|array[] $data Collection of data
	 * @since 0.10.0
	 */
	public function __construct(array $data)
	{
		foreach ($data as $values) {
			if( is_array($values)){
				$values = new TeamSettings($values);
			}
			if(  is_a($values, TeamSettings::class)){
				$this->settings[] = $values;
			}
		}
	}

	/**
	 * Create instance from saved data.
	 * @since 0.10.0
	 * @return SettingsApi
	 */
	public static function from_saved(){
		$saved = get_option(self::SETTING_NAME,[]);

		$data = [];
		if( ! empty($saved)){
			$saved = (array)json_decode($saved);
			foreach ($saved as  $value) {
				$data[] = new TeamSettings((array)$value);
			}
		}

		return new self($data);
	}

	/**
	 * Save data
	 *
	 * @since 0.10.0
	 * @return SettingsApi
	 */
	public function save(){
		$data = [];
		foreach ($this->settings as $setting) {
			$data[] = $setting->to_array();
		}
		update_option(self::SETTING_NAME, json_encode($data) );
		return $this;
	}

	/**
	 * Get team setting, by id
	 *
	 * @since 0.10.0
	 * @param string|int $account_id Account to search for
	 * @return TeamSettings
	 */
	public function get_by_account_id($account_id){
		foreach ($this->settings as $setting) {
			if( $account_id === $setting->get('account_id')){
				return $setting;
			}
		}
		throw new \Exception( 'Not found' );
	}


	 * Update team setting
	 *
	 * @since 0.10.0
	 * @param string|int $account_id Account to update
	 * @param TeamSettings $values New settings object
	 * @return SettingsApi
	 */
	public function update_by_account_id($account_id, TeamSettings $value){
		foreach ($this->settings as $key => $setting) {
			if( $account_id == $setting->get('account_id')){
				$this->settings[$key] = $value;
				return $this;
			}
		}
		throw new \Exception( 'Not found' );

	}
}
