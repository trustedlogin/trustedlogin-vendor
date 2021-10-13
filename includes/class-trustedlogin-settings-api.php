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
	protected $settings = [];
	public function __construct(array $data)
	{
		foreach ($data as $values) {
			$this->settings[] = new TeamSettings($values);
		}
	}


	public function get_by_account_id($account_id){
		foreach ($this->settings as $setting) {
			if( $account_id === $setting->get('account_id')){
				return $setting;
			}
		}
		throw new \Exception( 'Not found' );
	}

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
