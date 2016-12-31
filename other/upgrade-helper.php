<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 *
 * This file contains helper functions for upgrade.php
 */

if (!defined('SMF_VERSION'))
    die('No direct access!');

if (!function_exists('un_htmlspecialchars'))
{
    /**
     * Undo the voodoo htmlspecialchars does.
     *
     * @param $string
     * @return string
     */
    function un_htmlspecialchars($string)
    {
        return strtr($string, array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES)) + array('&#039;' => '\'', '&nbsp;' => ' '));
    }
}

if (!function_exists('text2words'))
{
    /**
     * Split a sentence into words.
     *
     * @param $text
     * @return array
     */
    function text2words($text)
    {
        // Step 1: Remove entities/things we don't consider words:
        $words = preg_replace('~(?:[\x0B\0\xA0\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~', ' ', $text);

        // Step 2: Entities we left to letters, where applicable, lowercase.
        $words = preg_replace('~([^&\d]|^)[#;]~', '$1 ', un_htmlspecialchars(strtolower($words)));

        // Step 3: Ready to split apart and index!
        $words = explode(' ', $words);
        $returned_words = array();
        foreach ($words as $word)
        {
            $word = trim($word, '-_\'');

            if ($word != '')
                $returned_words[] = substr($word, 0, 20);
        }

        return array_unique($returned_words);
    }
}

if (!function_exists('md5_hmac'))
{
    /**
     * Generate an MD5 hash using the HMAC method.
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    function md5_hmac($data, $key)
    {
        return hash_hmac('md5', $data, $key);
    }
}

/**
 * Clean the cache using the SMF 2.1 CacheAPI.
 * If coming from SMF 2.0 and below it should wipe the cache using the SMF backend.
 */
function upgrade_clean_cache()
{
    global $cacheAPI, $sourcedir;

    // Initialize the cache API if it does not have an instance yet.
    if (empty($cacheAPI))
    {
        require_once($sourcedir . '/Load.php');
        loadCacheAccelerator();
    }

    // Just fall back to Load.php's clean_cache function.
    clean_cache();
}

/**
 * Returns a list of member groups. Used to upgrade 1.0 and 1.1.
 *
 * @return array
 */
function getMemberGroups()
{
    global $smcFunc;
    static $member_groups = array();

    if (!empty($member_groups))
        return $member_groups;

    $request = $smcFunc['db_query']('', '
		SELECT group_name, id_group
		FROM {db_prefix}membergroups
		WHERE id_group = {int:admin_group} OR id_group > {int:old_group}',
        array(
            'admin_group' => 1,
            'old_group' => 7,
            'db_error_skip' => true,
        )
    );
    if ($request === false)
    {
        $request = $smcFunc['db_query']('', '
			SELECT membergroup, id_group
			FROM {db_prefix}membergroups
			WHERE id_group = {int:admin_group} OR id_group > {int:old_group}',
            array(
                'admin_group' => 1,
                'old_group' => 7,
                'db_error_skip' => true,
            )
        );
    }
    while ($row = $smcFunc['db_fetch_row']($request))
        $member_groups[trim($row[0])] = $row[1];
    $smcFunc['db_free_result']($request);

    return $member_groups;
}

/**
 * Database functions below here.
 */
/**
 * @param $rs
 * @return array|null
 */
function smf_mysql_fetch_assoc($rs)
{
    return mysqli_fetch_assoc($rs);
}

/**
 * @param $rs
 * @return array|null
 */
function smf_mysql_fetch_row($rs)
{
    return mysqli_fetch_row($rs);
}

/**
 * @param $rs
 */
function smf_mysql_free_result($rs)
{
    mysqli_free_result($rs);
}

/**
 * @param $rs
 * @return int|string
 */
function smf_mysql_insert_id($rs)
{
    return mysqli_insert_id($rs);
}

/**
 * @param $rs
 * @return int
 */
function smf_mysql_num_rows($rs)
{
    return mysqli_num_rows($rs);
}

/**
 * @param $string
 */
function smf_mysql_real_escape_string($string)
{
    global $db_connection;
    mysqli_real_escape_string($db_connection, $string);
}