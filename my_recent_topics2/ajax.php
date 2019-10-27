<?php
require_once('../SSI.php');
global $boarddir;
require_once($boarddir . '/my_recent_topics2/func.php');
echo ViewPagination2(my_recentTopics2());
die();
