<?php
trait TL_Licensing
{

	public function edd_has_licensing()
    {
        return function_exists('edd_software_licensing');
    }

    public function edd_get_licenses($email)
    {

        $keys = array();
        $_u = get_user_by('email', $email);

        if ($_u) {

            $licenses = edd_software_licensing()->get_license_keys_of_user($_u->ID, 0, 'all', true);

            foreach ($licenses as $license) {
                $children = edd_software_licensing()->get_child_licenses($license->ID);
                if ($children) {
                    foreach ($children as $child) {
                        $keys[] = edd_software_licensing()->get_license_key($child->ID);
                    }
                }

                $keys[] = edd_software_licensing()->get_license_key($license->ID);
            }
        }

        return (!empty($keys)) ? $keys : false;
    }

    public function edd_verify_license($key){
    	
    	$key = sanitize_text_field($key);

    	$license = new EDD_SL_License($key);

    	$this->dlog('license: '.print_r($license,true),__METHOD__);

    	return $license->exists;
    }

	/**
	 * @param $type
	 * @param $value
	 *
	 * @see Endpoint::verify_callback
	 *
	 * @return array|bool
	 */
    public function get_licenses_by($type, $value){

    	$this->dlog("type: $type | value: $value",__METHOD__);
    	
    	if (!in_array($type, array('email','key'))){
    		return false;
    	}

    	if ( $this->is_edd_store() && $this->edd_has_licensing() ){
    		if ('email' == $type){
    			return $this->edd_get_licenses($value);
    		} else if ('key' == $type) {
    			return $this->edd_verify_license($value);
    		}
    	} else if ($this->is_woo_store()){
    		// handle woo licensing
    	} 

		return false;
    	
    }

}