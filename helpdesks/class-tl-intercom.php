<?php
namespace TrustedLogin;

/**
 * Class: TrustedLogin - Intercom Integration
 *
 * @package tl-support-side
 * @version 0.0
 **/
class Intercom extends HelpDesk {

	const name = 'Intercom';

	const slug = 'intercom';

	const version = '0.0';

	const is_active = false;
}

$hl = new Intercom();
