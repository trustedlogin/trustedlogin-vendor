<?php
namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - HubSpot Integration
 *
 * @package tl-support-side
 * @version 0.0
 **/
class HubSpot extends HelpDesk {

	const NAME = 'HubSpot';

	const SLUG = 'hubspot';

	const VERSION = '0.0';

	const IS_ACTIVE = false;
}

$hl = new HubSpot();
