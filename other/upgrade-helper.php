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
    function un_htmlspecialchars($string)
    {
        return strtr($string, array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES)) + array('&#039;' => '\'', '&nbsp;' => ' '));
    }
}

if (!function_exists('text2words'))
{
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


// MD5 Encryption.
if (!function_exists('md5_hmac'))
{
    /**
     * @param string $data
     * @param string $key
     * @return string
     */
    function md5_hmac($data, $key)
    {
        return hash_hmac('md5', $data, $key);
    }
}