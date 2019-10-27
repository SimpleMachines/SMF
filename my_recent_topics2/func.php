<?php

function my_recentTopics2($start = 0, $num_recent = 60, $exclude_boards = null, $include_boards = null){
	global $context, $settings, $scripturl, $txt, $db_prefix, $user_info;
	global $modSettings, $smcFunc;


	$cache_id = 'c_id_'.$start.'_'.$num_recent.'_';	
	$cache_id .= '_' . $user_info['id'];

	

	if (!empty($modSettings['cache_enable'])) $recent_topics_arr = unserialize(cache_get_data($cache_id));
	if (!isset($recent_topics_arr) || !is_array($recent_topics_arr)) {

		// Find all the posts in distinct topics.  Newer ones will have higher IDs.
		$recent_topics_arr = array();
		$request = $smcFunc['db_query']('substring', '
			SELECT
				m.poster_time, ms.subject, m.id_topic, m.id_member, m.id_msg, b.id_board, b.name AS board_name, t.num_replies, t.num_views,
				IFNULL(mem.real_name, m.poster_name) AS poster_name, ' . ($user_info['is_guest'] ? '1 AS is_read, 0 AS new_from' : '
				IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= m.id_msg_modified AS is_read,
				IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ', SUBSTRING(m.body, 1, 384) AS body, m.smileys_enabled, m.icon, mem.id_group
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!$user_info['is_guest'] ? '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})' : '') . '				
			WHERE t.id_last_msg >= {int:min_message_id}
				' . (empty($exclude_boards) ? '' : '
				AND b.id_board NOT IN ({array_int:exclude_boards})') . '
				' . (empty($include_boards) ? '' : '
				AND b.id_board IN ({array_int:include_boards})') . '
				AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY t.id_last_msg DESC
			LIMIT '.(int)$start.', ' . (int)$num_recent,
			array(
				'current_member' => $user_info['id'],
				'include_boards' => empty($include_boards) ? '' : $include_boards,
				'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
				'min_message_id' => $modSettings['maxMsgID'] - 55 * min($num_recent, 5),
				'is_approved' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request)) $recent_topics_arr[] = $row;

		$request2 = $smcFunc['db_query']('', 'SELECT id_group, online_color FROM {db_prefix}membergroups', array());
		while ($row2 = $smcFunc['db_fetch_assoc']($request2)) $all_membergroups[] = $row2;
		foreach($all_membergroups AS $key1=>$value1){
			foreach($recent_topics_arr AS $key2=>$value2){
				if($value2['id_group'] == $value1['id_group']) {
					$recent_topics_arr[$key2]['online_color'] = $value1['online_color'];
				}
			}
		}

		
		if (!empty($modSettings['cache_enable'])) cache_put_data($cache_id, serialize($recent_topics_arr), 10);
	}
	return $recent_topics_arr;
}


if ($context['user']['is_guest']){
function ViewPagination2($last_topics_arr){
 
    global $user_info, $boardurl;
    $arr = array();
    if(count($last_topics_arr)>0){
        foreach ($last_topics_arr AS $key=>$value){
        
            $arr[] = '<a class="windowbg2 onerecent2" href="'.$boardurl.'/index.php?topic='.$last_topics_arr[$key]['id_topic'].'.msg'.$last_topics_arr[$key]['id_msg'].';topicseen#new">'
            . ($key + 1)
            .'. <strong>'.$last_topics_arr[$key]['subject']
            .(!$last_topics_arr[$key]['is_read'] ? '&nbsp<span class="new_posts">' . $txt['new'] . '</span>' : '')
            .'</strong> в ('
            .$last_topics_arr[$key]['board_name'].')'
            .' от <span'
            .((isset($last_topics_arr[$key]['online_color']) && $last_topics_arr[$key]['online_color']!='')?(' style="color: '.$last_topics_arr[$key]['online_color'].'"'):('')).'>'
            .$last_topics_arr[$key]['poster_name'].' </span>('
            .timeformat($last_topics_arr[$key]['poster_time']).')</span></a>';
 
        }
    }

	return json_encode($arr);
	die();
	 
}
}
if ($context['user']['is_logged']){
function ViewPagination2($last_topics_arr){
 
    global $user_info, $boardurl,  $txt;
    $arr = array();
    if(count($last_topics_arr)>0){
        foreach ($last_topics_arr AS $key=>$value){
        
            $arr[] = '<a class="windowbg2 onerecent2" href="'.$boardurl.'/index.php?topic='.$last_topics_arr[$key]['id_topic'].'.msg'.$last_topics_arr[$key]['new_from'].';topicseen#new">'
            . ($key + 1)
            .'. <strong>'.$last_topics_arr[$key]['subject']
             .(!$last_topics_arr[$key]['is_read'] ? '&nbsp<span class="new_posts">' . $txt['new'] . '</span>' : '')
            .'</strong> в ('
            .$last_topics_arr[$key]['board_name'].')'
            .' от <span'
            .((isset($last_topics_arr[$key]['online_color']) && $last_topics_arr[$key]['online_color']!='')?(' style="color: '.$last_topics_arr[$key]['online_color'].'"'):('')).'>'
            .$last_topics_arr[$key]['poster_name'].' </span>('
            .timeformat($last_topics_arr[$key]['poster_time']).')</span></a>';
 
        }
    }

	return json_encode($arr);
	die();
	 
}
}
