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

	const TEAM_SETTING_NAME = 'trustedlogin_vendor_team_settings';
	const HELPSCOUT_SETTING_NAME = 'trustedlogin_helpscout_settings';

	/**
	 * @var TeamSettings[]
	 * @since 0.10.0
	 */
	protected $team_settings = [];


	/**
	 * @var array
	 * @since 0.10.0
	 */
	protected $helpscout_data = [];

	/**
	 * @param TeamSettings[]|array[] $team_data Collection of team data
	 * @param array $helpscout_data Helpscout settings data
	 * @since 0.10.0
	 */
	public function __construct(array $team_data,array $helpscout_data = [])
	{
		foreach ($team_data as $values) {
			if( is_array($values)){
				$values = new TeamSettings($values);
			}
			if(  is_a($values, TeamSettings::class)){
				$this->team_settings[] = $values;
			}
		}
		$this->helpscout_data = $helpscout_data;
	}

	/**
	 * Create instance from saved data.
	 * @since 0.10.0
	 * @return SettingsApi
	 */
	public static function from_saved(){
		$saved = get_option(self::TEAM_SETTING_NAME,[]);

		$data = [];
		if( ! empty($saved)){
			$saved = (array)json_decode($saved);
			foreach ($saved as  $value) {
				$data[] = new TeamSettings((array)$value);
			}
		}
		$helpscout_data = get_option(self::HELPSCOUT_SETTING_NAME,[]);
		if( ! is_array($helpscout_data)){
			$helpscout_data = [];
		}

		return new self($data,$helpscout_data);
	}

	/**
	 * Save data
	 *
	 * @since 0.10.0
	 * @return SettingsApi
	 */
	public function save(){
		$data = [];
		foreach ($this->team_settings as $setting) {
			$data[] = $setting->to_array();
		}
		update_option(self::TEAM_SETTING_NAME, json_encode($data) );
		update_option(self::HELPSCOUT_SETTING_NAME, $this->get_helpscout_data());
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
		foreach ($this->team_settings as $setting) {
			if( $account_id === $setting->get('account_id')){
				return $setting;
			}
		}
		throw new \Exception( 'Not found' );
	}

	/*
	 * Update team setting
	 *
	 * @since 0.10.0
	 * @param TeamSettings $values New settings object
	 * @return SettingsApi
	 */
	public function update_by_account_id(TeamSettings $value){
		foreach ($this->team_settings as $key => $setting) {
			if( $value->get( 'account_id') == $setting->get('account_id')){
				$this->team_settings[$key] = $value;
				return $this;
			}
		}
		throw new \Exception( 'Not found' );

	}

	/**
	 * Check if setting is in collection
	 * @since 0.10.0
	 * @param string $account_id
	 * @return bool
	 */
	public function has_setting( $account_id ){
		foreach ($this->team_settings as $setting) {
			if( $account_id == $setting->get('account_id')){
				return true;
			}

		}
		return false;
	}

	/**
	 * Add or update a setting to collection
	 * @since 0.10.0
	 * @param TeamSetting $setting
	 * @return $this
	 */
	public function add_setting(TeamSettings $setting ){
		//If we have it already, update
		if($this->has_setting($setting->get('account_id'))){
			$this->update_by_account_id($setting);
			return $this;
		}
		//add it to collection
		$this->team_settings[] = $setting;
		return $this;
	}

	/**
	* Reset all data
	*
	* @since 0.10.0
	* @return $this
	*/
	public function reset(){
		$this->team_settings = [];
		$this->helpscout_data = [];
		return $this;
	}

	/**
	 * Set helpscout data
	 *
	 * @since 0.10.0
     * @param array $helpscout_data
	 * @return $this
	 */
	public function set_helpscout_data(array $helpscout_data){
		$this->helpscout_data = $helpscout_data;
		return $this;
	}

	/**
	 * Get helpscout data
	 *
	 * @since 0.10.0
     * @return array
	 */
	public function get_helpscout_data(){
		return [
			'secret' => isset($this->helpscout_data['secret']) ?$this->helpscout_data['secret'] :"",
			'callback' => isset($this->helpscout_data['callback']) ?$this->helpscout_data['callback'] :"",
		];
	}
	/**
	 * Convert to array of arrays
	 *
	 * @since 0.10.0
	 * @return array
	 */
	public function to_array(){
		$data = [
			'teams' => [],
			'helpscout' => $this->get_helpscout_data()
		];
		foreach ($this->team_settings as $setting) {
			$data['teams'][] = $setting->to_array();
		}
		return $data;
	}
}
