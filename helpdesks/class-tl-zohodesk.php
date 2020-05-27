<?php
namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - ZohoDesk Integration
 *
 * @package tl-support-side
 * @version 0.0
 **/
class ZohoDesk extends HelpDesk {

	const NAME = 'ZohoDesk';

	const SLUG = 'zohodesk';

	const VERSION = '0.0';

	const IS_ACTIVE = false;
}

$hl = new ZohoDesk();
