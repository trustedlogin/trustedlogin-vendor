<?php
/**
 * Adds support for the Intercom helpdesk
 *
 * @package TrustedLogin\Vendor\HelpDesks
 */

namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - Intercom Integration
 *
 * @package tl-support-side
 * @version 0.0
 **/
class Intercom extends HelpDesk {

	const NAME = 'Intercom';

	const SLUG = 'intercom';

	const VERSION = '0.0';

	const IS_ACTIVE = false;
}

$hl = new Intercom();
