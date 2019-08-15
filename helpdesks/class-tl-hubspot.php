<?php
namespace TrustedLogin;

/**
 * Class: TrustedLogin - HubSpot Integration
 *
 * @package tl-support-side
 * @version 0.0
 **/
class HubSpot extends HelpDesk {

	const name = 'HubSpot';

	const slug = 'hubspot';

	const version = '0.0';

	const is_active = false;
}

$hl = new HubSpot();
