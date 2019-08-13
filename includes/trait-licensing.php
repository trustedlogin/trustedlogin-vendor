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
    	// $license_id = edd_software_licensing()->licenses_db->get_column_by('id','license_key', $key );

    	$license = new EDD_SL_License($key);

    	return $license->exists;
    }

    /**
     * Helper function: Check if the current site is an EDD store
     *
     * @since 0.2.0
     * @return Boolean
     **/
    public function is_edd_store()
    {
        return class_exists('Easy Digital Downloads');
    }

    /**
    * Helper function: Check if the current site is Woocommerce store
    * 
    * @since 0.8.0
    * @return Boolean
    **/
    public function is_woo_store(){
    	return class_exists('woocommerce');
    }

    public function get_licenses_by($type, $value){

    	$this->dlog("type: $type | value: $value",__METHOD__);
    	
    	if (!in_array($type, array('email','key'))){
    		return false;
    	}

    	if ($this->is_edd_store() && $this->has_edd_licensing()){
    		if ('email' == $type){
    			return $this->edd_get_licenses($value);
    		} else {
    			return $this->edd_verify_license($value);
    		}
    	} else if ($this->is_woo_store()){
    		// handle woo licensing
    	} 

		return false;
    	
    }

}