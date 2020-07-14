<?php
/**
 * Adds support for the HubSpot helpdesk
 *
 * @package TrustedLogin\Vendor\HelpDesks
 */

namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - HubSpot Integration
 *
 */
class HubSpot extends HelpDesk {

	const NAME = 'HubSpot';

	const SLUG = 'hubspot';

	const VERSION = '0.0';

	const IS_ACTIVE = false;
}

$hl = new HubSpot();
