<?php
namespace TrustedLogin;

interface License_Generator {

	const name;

	const slug;

	public function is_enabled();

	public function get_license_keys_by_email();

}