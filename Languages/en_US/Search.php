<?php

// Version: 3.0 Alpha 2; Search

// Comma-separated list of words that should be ignored when searching in this language. Translators should NOT just translate the words in this list. Instead, replace them with common words that should be ignored when searching in the target language.
$txt['search_stopwords'] = 'a,about,an,are,as,at,be,by,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with';

// Set to 0 if this language uses accented characters like "é", "ñ", "ç", etc.; set to 1 if it does not.
$txt['search_ignore_accents_by_default'] = '1';

$txt['search_stopwords_custom'] = 'Words to ignore when searching';
$txt['search_stopwords_permanent'] = 'The following words are always ignored:<br>{list}';

$txt['set_parameters'] = 'Set Search Parameters';
$txt['choose_board'] = 'Choose boards to search in, or search all';
$txt['all_words'] = 'Match all words';
$txt['any_words'] = 'Match any words';
$txt['by_user'] = 'By user';

$txt['search_post_age'] = 'Message age';
$txt['search_age_range'] = 'between {min} and {max} days';
$txt['search_options'] = 'Options';
$txt['search_ignore_accents'] = 'Ignore accents, etc.';
$txt['search_show_complete_messages'] = 'Show results as messages';
$txt['search_subject_only'] = 'Search in topic subjects only';
$txt['search_relevance'] = 'Relevance';
$txt['search_date_posted'] = 'Date Posted';
$txt['search_order'] = 'Search order';
$txt['search_orderby_relevant_first'] = 'Most relevant results first';
$txt['search_orderby_large_first'] = 'Largest topics first';
$txt['search_orderby_small_first'] = 'Smallest topics first';
$txt['search_orderby_recent_first'] = 'Most recent topics first';
$txt['search_orderby_old_first'] = 'Oldest topics first';
$txt['search_visual_verification_label'] = 'Verification';
$txt['search_visual_verification_desc'] = 'Please enter the code in the image above to use search.';

$txt['search_specific_topic'] = 'Searching only posts in "{topic}"';

$txt['mods_cat_search'] = 'Search';
$txt['groups_search_posts'] = 'Membergroups with access to the search function';
$txt['search_results_per_page'] = 'Number of search results per page';
$txt['search_weight_frequency'] = 'Relative search weight for number of matching messages within a topic';
$txt['search_weight_age'] = 'Relative search weight for age of last matching message';
$txt['search_weight_length'] = 'Relative search weight for topic length';
$txt['search_weight_subject'] = 'Relative search weight for a matching subject';
$txt['search_weight_first_message'] = 'Relative search weight for a first message match';
$txt['search_weight_sticky'] = 'Relative search weight for a sticky topic';

$txt['search_settings_desc'] = 'Here you can changes the basic settings of the search function.';
$txt['search_settings_title'] = 'Search Settings';

$txt['search_weights_desc'] = 'Here you can change the individual components of the relevance rating. ';
$txt['search_weights_title'] = 'Search - weights';
$txt['search_weights_total'] = 'Total';
$txt['search_weights_save'] = 'Save';

$txt['search_method_desc'] = 'Here you can set the way search is powered.';
$txt['search_method_title'] = 'Search - method';
$txt['search_method_save'] = 'Save';
$txt['search_method_messages_table_space'] = 'Space used by forum messages in the database';
$txt['search_method_messages_index_space'] = 'Space used to index messages in the database';
$txt['search_method_kilobytes'] = 'KB';
$txt['search_method_no_index_exists'] = '{index} does not currently exist';
$txt['search_method_fulltext_cannot_create'] = 'A fulltext index cannot be created because fulltext search is not supported for your database engine and version';
$txt['search_method_index_already_exists'] = '{index} already created';
$txt['search_method_index_partial'] = '{index} partially created';
// This string is used in a javascript confirmation popup; don't use entities.
$txt['search_method_fulltext_warning'] = 'In order to be able to use fulltext search, you must first create a fulltext index.';

$txt['search_index'] = 'Search index';
$txt['search_index_none'] = 'No index';
$txt['search_index_generic'] = '{index} index';
$txt['search_index_fulltext'] = 'Fulltext index';
$txt['search_index_fulltext_desc'] = 'A basic index provided by the database engine.';
$txt['search_index_custom'] = 'Custom index';
$txt['search_index_custom_desc'] = 'A custom index used in earlier versions of SMF.<br>Upgrading to a parsed index is recommended.';
$txt['search_index_parsed'] = 'Parsed index';
$txt['search_index_parsed_desc'] = 'An optimized index tailored specifically for SMF.';
$txt['search_index_sphinx'] = 'Sphinx index';
$txt['search_index_sphinx_desc'] = 'The admin panel only allows to switch between search indexes. To adjust further Sphinx settings, use the sphinx_config.php tool.';
$txt['search_index_label'] = 'Index';
$txt['search_index_size'] = 'Size:';
$txt['search_index_remove'] = 'Remove';
$txt['search_index_resume'] = 'Resume';
// This string is used in a javascript confirmation popup; don't use entities.
$txt['search_index_custom_warning'] = 'In order to be able to use a custom index search, you must first create a custom index.';

$txt['search_force_index'] = 'Force the use of a search index';
$txt['search_match_words'] = 'Match whole words only';
$txt['search_max_results'] = 'Maximum results to show';
$txt['search_max_results_disable'] = '(0: no limit)';
$txt['search_floodcontrol_time'] = 'Time required between searches from same user';
$txt['search_floodcontrol_time_desc'] = '(0 for no limit, in seconds)';

$txt['search_create_index'] = 'Create index';
$txt['search_create_index_why'] = 'Why create a search index?';
$txt['search_create_index_start'] = 'Create';
$txt['search_predefined'] = 'Pre-defined profile';
$txt['search_predefined_small'] = 'Small sized index';
$txt['search_predefined_moderate'] = 'Moderate sized index';
$txt['search_predefined_large'] = 'Large sized index';
$txt['search_create_index_continue'] = 'Continue';
$txt['search_create_index_not_ready'] = 'SMF is currently creating a search index of your messages. To avoid overloading your server, the process has been temporarily paused. It should automatically continue in a few seconds. If it does not, please click continue below.';
$txt['search_create_index_progress'] = 'Progress';
$txt['search_create_index_done'] = 'Custom search index successfully created.';
$txt['search_create_index_done_link'] = 'Continue';
$txt['search_double_index'] = 'You have currently created multiple indexes on the messages table. For best performance it is advisable to use only one.';

$txt['search_error_indexed_chars'] = 'Invalid number of indexed characters. At least 3 characters are needed for a useful index.';
$txt['search_error_max_percentage'] = 'Invalid percentage of words to be skipped. Use a value of at least 5%.';
$txt['error_string_too_long'] = '{0, plural,
	one {Search string must be less than # character long.}
	other {Search string must be less than # characters long.}
}';

$txt['search_adjust_query'] = 'Adjust Search Parameters';
$txt['search_warning_ignored_word'] = 'The following term has been ignored in your search because it is too short';
$txt['search_warning_ignored_words'] = 'The following terms have been ignored in your search because they are too short';
$txt['search_adjust_submit'] = 'Revise Search';
$txt['search_did_you_mean'] = 'You may have meant to search for {suggested_query}.';

$txt['search_example'] = '<em>e.g.</em> Orwell "Animal Farm" -movie';

$txt['search_engines_description'] = 'From this area you can decide in what detail you wish to track search engines as they index your forum as well as review search engine logs.';
$txt['spider_mode'] = 'Search Engine Tracking level';
$txt['spider_mode_note'] = 'Note that logging of every spider action only occurs if tracking is set to either "moderate" or "aggressive". Detail of every spider’s action is only logged if tracking is set to "aggressive".';
$txt['spider_mode_off'] = 'Disabled';
$txt['spider_mode_standard'] = 'Standard';
$txt['spider_mode_high'] = 'Moderate';
$txt['spider_mode_vhigh'] = 'Aggressive';
$txt['spider_settings_desc'] = 'You can change settings for spider tracking from this page. Note, if you wish to enable automatic pruning of the hit logs you can set this up <a href="%1$s">here</a>';

$txt['spider_group'] = 'Apply restrictive permissions from group';
$txt['spider_group_note'] = 'To enable you to stop spiders indexing some pages.';
$txt['spider_group_none'] = 'Disabled';

$txt['show_spider_online'] = 'Show spiders in the online list';
$txt['show_spider_online_no'] = 'Not at all';
$txt['show_spider_online_summary'] = 'Show spider quantity';
$txt['show_spider_online_detail'] = 'Show spider names';
$txt['show_spider_online_detail_admin'] = 'Show spider names - admin only';

$txt['spider_name'] = 'Spider Name';
$txt['spider_last_seen'] = 'Last Seen';
$txt['spider_last_never'] = 'Never';
$txt['spider_agent'] = 'User Agent';
$txt['spider_ip_info'] = 'IP Addresses';
$txt['spiders_add'] = 'Add new Spider';
$txt['spiders_edit'] = 'Edit Spider';
$txt['spiders_remove_selected'] = 'Remove selected';
$txt['spider_remove_selected_confirm'] = 'Are you sure you want to remove these spiders?-n-All associated statistics will also be deleted!';
$txt['spiders_no_entries'] = 'There are currently no spiders configured.';

$txt['add_spider_desc'] = 'From this page you can edit the parameters against which a spider is categorized. If a guest’s user agent/IP address matches those entered below it will be detected as a search engine spider and tracked as per the forum preferences.';
$txt['spider_name_desc'] = 'Name by which the spider will be referred.';
$txt['spider_agent_desc'] = 'User agent associated with this spider.';
$txt['spider_ip_info_desc'] = 'Comma separated list of IP addresses associated with this spider.';

$txt['spider'] = 'Spider';
$txt['spider_time'] = 'Time';
$txt['spider_viewing'] = 'Viewing';
$txt['spider_logs_empty'] = 'There are currently no spider log entries.';
$txt['spider_logs_info'] = 'Note that logging of every spider action only occurs if tracking is set to either "moderate" or "aggressive". Detail of every spider’s action is only logged if tracking is set to "aggressive".';
$txt['spider_disabled'] = 'Disabled';
$txt['spider_log_empty_log'] = 'Clear Log';
$txt['spider_log_empty_log_confirm'] = 'Are you sure you want to completely clear the log';

$txt['spider_logs_delete'] = 'Delete Entries';
$txt['spider_logs_delete_older'] = 'Delete all entries older than';
$txt['spider_logs_delete_day'] = 'days.';
$txt['spider_logs_delete_submit'] = 'Delete';

// argument: an HTML <input> element.
$txt['spider_stats_delete_older'] = 'Delete all spider statistics from spiders not seen in {input} days.';

// Don't use entities in the below string.
$txt['spider_logs_delete_confirm'] = 'Are you sure you wish to empty out all log entries?';

$txt['spider_stats_select_month'] = 'Jump to Month';
$txt['spider_stats_page_hits'] = 'Page Hits';
$txt['spider_stats_no_entries'] = 'There are currently no spider statistics available.';

?>