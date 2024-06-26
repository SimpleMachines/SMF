<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Unicode;

if (!defined('SMF')) {
	die('No direct access...');
}

/**
 * Helper function for SMF\Localization\MessageFormatter::formatMessage.
 *
 * Rules compiled from:
 * https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/currencyData.json
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Information about different currencies
 */
function currencies(): array
{
	return [
		'AED' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'AFN' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'ALL' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'AMD' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'AOA' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'ARS' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'AUD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'AWG' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'AZN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BAM' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BBD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BDT' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BGN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BHD' => [
			'digits' => 3,
			'rounding' => 0,
		],
		'BIF' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'BMD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BND' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BOB' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BRL' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BSD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BTN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BWP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BYN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'BZD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'CAD' => [
			'digits' => 2,
			'rounding' => 0,
			'cashRounding' => 5,
		],
		'CDF' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'CHF' => [
			'digits' => 2,
			'rounding' => 0,
			'cashRounding' => 5,
		],
		'CLP' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'CNY' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'COP' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'CRC' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'CUP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'CVE' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'CZK' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'DEFAULT' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'DJF' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'DKK' => [
			'digits' => 2,
			'rounding' => 0,
			'cashRounding' => 50,
		],
		'DOP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'DZD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'EGP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'ERN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'ETB' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'EUR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'FJD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'FKP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'GBP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'GEL' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'GHS' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'GIP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'GMD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'GNF' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'GTQ' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'GYD' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'HKD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'HNL' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'HTG' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'HUF' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'IDR' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'ILS' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'INR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'IQD' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'IRR' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'ISK' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'JMD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'JOD' => [
			'digits' => 3,
			'rounding' => 0,
		],
		'JPY' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'KES' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'KGS' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'KHR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'KMF' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'KPW' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'KRW' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'KWD' => [
			'digits' => 3,
			'rounding' => 0,
		],
		'KYD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'KZT' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'LAK' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'LBP' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'LKR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'LRD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'LSL' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'LYD' => [
			'digits' => 3,
			'rounding' => 0,
		],
		'MAD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MDL' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MGA' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'MKD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MMK' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'MNT' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'MOP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MRU' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MUR' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'MVR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MWK' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MXN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MYR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'MZN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'NAD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'NGN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'NIO' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'NOK' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'NPR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'NZD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'OMR' => [
			'digits' => 3,
			'rounding' => 0,
		],
		'PAB' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'PEN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'PGK' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'PHP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'PKR' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'PLN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'PYG' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'QAR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'RON' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'RSD' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'RUB' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'RWF' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'SAR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SBD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SCR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SDG' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SEK' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'SGD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SHP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SLE' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SOS' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'SRD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SSP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'STN' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'SYP' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'SZL' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'THB' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'TJS' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'TMT' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'TND' => [
			'digits' => 3,
			'rounding' => 0,
		],
		'TOP' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'TRY' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'TTD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'TWD' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'TZS' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'UAH' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'UGX' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'USD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'UYU' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'UZS' => [
			'digits' => 2,
			'rounding' => 0,
			'cashDigits' => 0,
			'cashRounding' => 0,
		],
		'VES' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'VND' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'VUV' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'WST' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'XAF' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'XCD' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'XCG' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'XOF' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'XPF' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'YER' => [
			'digits' => 0,
			'rounding' => 0,
		],
		'ZAR' => [
			'digits' => 2,
			'rounding' => 0,
		],
		'ZMW' => [
			'digits' => 2,
			'rounding' => 0,
		],
	];
}

/**
 * Helper function for SMF\Localization\MessageFormatter::formatMessage.
 *
 * Rules compiled from:
 * https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/currencyData.json
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Information about currencies used in different countries
 */
function country_currencies(): array
{
	return [
		'AC' => [
			'SHP',
		],
		'AD' => [
			'EUR',
		],
		'AE' => [
			'AED',
		],
		'AF' => [
			'AFN',
		],
		'AG' => [
			'XCD',
		],
		'AI' => [
			'XCD',
		],
		'AL' => [
			'ALL',
		],
		'AM' => [
			'AMD',
		],
		'AO' => [
			'AOA',
		],
		'AR' => [
			'ARS',
		],
		'AS' => [
			'USD',
		],
		'AT' => [
			'EUR',
		],
		'AU' => [
			'AUD',
		],
		'AW' => [
			'AWG',
		],
		'AX' => [
			'EUR',
		],
		'AZ' => [
			'AZN',
		],
		'BA' => [
			'BAM',
		],
		'BB' => [
			'BBD',
		],
		'BD' => [
			'BDT',
		],
		'BE' => [
			'EUR',
		],
		'BF' => [
			'XOF',
		],
		'BG' => [
			'BGN',
		],
		'BH' => [
			'BHD',
		],
		'BI' => [
			'BIF',
		],
		'BJ' => [
			'XOF',
		],
		'BL' => [
			'EUR',
		],
		'BM' => [
			'BMD',
		],
		'BN' => [
			'BND',
		],
		'BO' => [
			'BOB',
		],
		'BQ' => [
			'USD',
		],
		'BR' => [
			'BRL',
		],
		'BS' => [
			'BSD',
		],
		'BT' => [
			'BTN',
			'INR',
		],
		'BV' => [
			'NOK',
		],
		'BW' => [
			'BWP',
		],
		'BY' => [
			'BYN',
		],
		'BZ' => [
			'BZD',
		],
		'CA' => [
			'CAD',
		],
		'CC' => [
			'AUD',
		],
		'CD' => [
			'CDF',
		],
		'CF' => [
			'XAF',
		],
		'CG' => [
			'XAF',
		],
		'CH' => [
			'CHF',
		],
		'CI' => [
			'XOF',
		],
		'CK' => [
			'NZD',
		],
		'CL' => [
			'CLP',
		],
		'CM' => [
			'XAF',
		],
		'CN' => [
			'CNY',
		],
		'CO' => [
			'COP',
		],
		'CR' => [
			'CRC',
		],
		'CU' => [
			'CUP',
		],
		'CV' => [
			'CVE',
		],
		'CW' => [
			'XCG',
		],
		'CX' => [
			'AUD',
		],
		'CY' => [
			'EUR',
		],
		'CZ' => [
			'CZK',
		],
		'DE' => [
			'EUR',
		],
		'DG' => [
			'USD',
		],
		'DJ' => [
			'DJF',
		],
		'DK' => [
			'DKK',
		],
		'DM' => [
			'XCD',
		],
		'DO' => [
			'DOP',
		],
		'DZ' => [
			'DZD',
		],
		'EA' => [
			'EUR',
		],
		'EC' => [
			'USD',
		],
		'EE' => [
			'EUR',
		],
		'EG' => [
			'EGP',
		],
		'EH' => [
			'MAD',
		],
		'ER' => [
			'ERN',
		],
		'ES' => [
			'EUR',
		],
		'ET' => [
			'ETB',
		],
		'EU' => [
			'EUR',
		],
		'FI' => [
			'EUR',
		],
		'FJ' => [
			'FJD',
		],
		'FK' => [
			'FKP',
		],
		'FM' => [
			'USD',
		],
		'FO' => [
			'DKK',
		],
		'FR' => [
			'EUR',
		],
		'GA' => [
			'XAF',
		],
		'GB' => [
			'GBP',
		],
		'GD' => [
			'XCD',
		],
		'GE' => [
			'GEL',
		],
		'GF' => [
			'EUR',
		],
		'GG' => [
			'GBP',
		],
		'GH' => [
			'GHS',
		],
		'GI' => [
			'GIP',
		],
		'GL' => [
			'DKK',
		],
		'GM' => [
			'GMD',
		],
		'GN' => [
			'GNF',
		],
		'GP' => [
			'EUR',
		],
		'GQ' => [
			'XAF',
		],
		'GR' => [
			'EUR',
		],
		'GS' => [
			'GBP',
		],
		'GT' => [
			'GTQ',
		],
		'GU' => [
			'USD',
		],
		'GW' => [
			'XOF',
		],
		'GY' => [
			'GYD',
		],
		'HK' => [
			'HKD',
		],
		'HM' => [
			'AUD',
		],
		'HN' => [
			'HNL',
		],
		'HR' => [
			'EUR',
		],
		'HT' => [
			'HTG',
			'USD',
		],
		'HU' => [
			'HUF',
		],
		'IC' => [
			'EUR',
		],
		'ID' => [
			'IDR',
		],
		'IE' => [
			'EUR',
		],
		'IL' => [
			'ILS',
		],
		'IM' => [
			'GBP',
		],
		'IN' => [
			'INR',
		],
		'IO' => [
			'USD',
		],
		'IQ' => [
			'IQD',
		],
		'IR' => [
			'IRR',
		],
		'IS' => [
			'ISK',
		],
		'IT' => [
			'EUR',
		],
		'JE' => [
			'GBP',
		],
		'JM' => [
			'JMD',
		],
		'JO' => [
			'JOD',
		],
		'JP' => [
			'JPY',
		],
		'KE' => [
			'KES',
		],
		'KG' => [
			'KGS',
		],
		'KH' => [
			'KHR',
		],
		'KI' => [
			'AUD',
		],
		'KM' => [
			'KMF',
		],
		'KN' => [
			'XCD',
		],
		'KP' => [
			'KPW',
		],
		'KR' => [
			'KRW',
		],
		'KW' => [
			'KWD',
		],
		'KY' => [
			'KYD',
		],
		'KZ' => [
			'KZT',
		],
		'LA' => [
			'LAK',
		],
		'LB' => [
			'LBP',
		],
		'LC' => [
			'XCD',
		],
		'LI' => [
			'CHF',
		],
		'LK' => [
			'LKR',
		],
		'LR' => [
			'LRD',
		],
		'LS' => [
			'LSL',
			'ZAR',
		],
		'LT' => [
			'EUR',
		],
		'LU' => [
			'EUR',
		],
		'LV' => [
			'EUR',
		],
		'LY' => [
			'LYD',
		],
		'MA' => [
			'MAD',
		],
		'MC' => [
			'EUR',
		],
		'MD' => [
			'MDL',
		],
		'ME' => [
			'EUR',
		],
		'MF' => [
			'EUR',
		],
		'MG' => [
			'MGA',
		],
		'MH' => [
			'USD',
		],
		'MK' => [
			'MKD',
		],
		'ML' => [
			'XOF',
		],
		'MM' => [
			'MMK',
		],
		'MN' => [
			'MNT',
		],
		'MO' => [
			'MOP',
		],
		'MP' => [
			'USD',
		],
		'MQ' => [
			'EUR',
		],
		'MR' => [
			'MRU',
		],
		'MS' => [
			'XCD',
		],
		'MT' => [
			'EUR',
		],
		'MU' => [
			'MUR',
		],
		'MV' => [
			'MVR',
		],
		'MW' => [
			'MWK',
		],
		'MX' => [
			'MXN',
		],
		'MY' => [
			'MYR',
		],
		'MZ' => [
			'MZN',
		],
		'NA' => [
			'NAD',
			'ZAR',
		],
		'NC' => [
			'XPF',
		],
		'NE' => [
			'XOF',
		],
		'NF' => [
			'AUD',
		],
		'NG' => [
			'NGN',
		],
		'NI' => [
			'NIO',
		],
		'NL' => [
			'EUR',
		],
		'NO' => [
			'NOK',
		],
		'NP' => [
			'NPR',
		],
		'NR' => [
			'AUD',
		],
		'NU' => [
			'NZD',
		],
		'NZ' => [
			'NZD',
		],
		'OM' => [
			'OMR',
		],
		'PA' => [
			'PAB',
			'USD',
		],
		'PE' => [
			'PEN',
		],
		'PF' => [
			'XPF',
		],
		'PG' => [
			'PGK',
		],
		'PH' => [
			'PHP',
		],
		'PK' => [
			'PKR',
		],
		'PL' => [
			'PLN',
		],
		'PM' => [
			'EUR',
		],
		'PN' => [
			'NZD',
		],
		'PR' => [
			'USD',
		],
		'PS' => [
			'ILS',
			'JOD',
		],
		'PT' => [
			'EUR',
		],
		'PW' => [
			'USD',
		],
		'PY' => [
			'PYG',
		],
		'QA' => [
			'QAR',
		],
		'RE' => [
			'EUR',
		],
		'RO' => [
			'RON',
		],
		'RS' => [
			'RSD',
		],
		'RU' => [
			'RUB',
		],
		'RW' => [
			'RWF',
		],
		'SA' => [
			'SAR',
		],
		'SB' => [
			'SBD',
		],
		'SC' => [
			'SCR',
		],
		'SD' => [
			'SDG',
		],
		'SE' => [
			'SEK',
		],
		'SG' => [
			'SGD',
		],
		'SH' => [
			'SHP',
		],
		'SI' => [
			'EUR',
		],
		'SJ' => [
			'NOK',
		],
		'SK' => [
			'EUR',
		],
		'SL' => [
			'SLE',
		],
		'SM' => [
			'EUR',
		],
		'SN' => [
			'XOF',
		],
		'SO' => [
			'SOS',
		],
		'SR' => [
			'SRD',
		],
		'SS' => [
			'SSP',
		],
		'ST' => [
			'STN',
		],
		'SV' => [
			'USD',
		],
		'SX' => [
			'XCG',
		],
		'SY' => [
			'SYP',
		],
		'SZ' => [
			'SZL',
		],
		'TA' => [
			'GBP',
		],
		'TC' => [
			'USD',
		],
		'TD' => [
			'XAF',
		],
		'TF' => [
			'EUR',
		],
		'TG' => [
			'XOF',
		],
		'TH' => [
			'THB',
		],
		'TJ' => [
			'TJS',
		],
		'TK' => [
			'NZD',
		],
		'TL' => [
			'USD',
		],
		'TM' => [
			'TMT',
		],
		'TN' => [
			'TND',
		],
		'TO' => [
			'TOP',
		],
		'TR' => [
			'TRY',
		],
		'TT' => [
			'TTD',
		],
		'TV' => [
			'AUD',
		],
		'TW' => [
			'TWD',
		],
		'TZ' => [
			'TZS',
		],
		'UA' => [
			'UAH',
		],
		'UG' => [
			'UGX',
		],
		'UM' => [
			'USD',
		],
		'US' => [
			'USD',
		],
		'UY' => [
			'UYU',
		],
		'UZ' => [
			'UZS',
		],
		'VA' => [
			'EUR',
		],
		'VC' => [
			'XCD',
		],
		'VE' => [
			'VES',
		],
		'VG' => [
			'USD',
		],
		'VI' => [
			'USD',
		],
		'VN' => [
			'VND',
		],
		'VU' => [
			'VUV',
		],
		'WF' => [
			'XPF',
		],
		'WS' => [
			'WST',
		],
		'XK' => [
			'EUR',
		],
		'YE' => [
			'YER',
		],
		'YT' => [
			'EUR',
		],
		'ZA' => [
			'ZAR',
		],
		'ZM' => [
			'ZMW',
		],
		'ZW' => [
			'USD',
		],
	];
}

?>