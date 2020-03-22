<?php
namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - ZohoDesk Integration
 *
 * @package tl-support-side
 * @version 0.0
 **/
class ZohoDesk extends HelpDesk {

	const name = 'ZohoDesk';

	const slug = 'zohodesk';

	const version = '0.0';

	const is_active = false;
}

$hl = new ZohoDesk();
