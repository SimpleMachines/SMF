<?php
global $boarddir;
require_once($boarddir . '/my_recent_topics2/func.php');
echo '<script src="/my_recent_topics2/recent_topics2.js" type="text/javascript"></script>';
echo '
<style>
	#my_recent_topics2 {
	    background-image: linear-gradient(to bottom,#fff 0%,#f1f3f5 95%);
        border: 1px solid #ddd;
        display:block;
        box-sizing: border-box;
		padding: 5px 12px 10px 12px;
        margin: 6px 12px 6px 12px;
        font-size: .9em;
	}
	.sub1 {
	   display: flex;
	   padding: 6px 0px 0px 0px;
	}
	.sub2 {
	   font-weight: 700;
	   padding: 2px 0px 4px 60px;
	}
#my_recent{
    width:100%;
}
#left_recent_col{
    float:left;
    min-width:49.5%;
    max-width:49.5%;
}
#right_recent_col{
    float:left;
    min-width:49.5%;
    max-width:49.5%;
    margin-left:1%;
}
.onerecent2{
    background-image: linear-gradient(to bottom,#fff 0%,#f1f3f5 95%);
    border: 1px solid #ddd;
    display:block;
    margin:3px 0;
    padding:1px 4px;
    text-decoration:none!important;
    min-height:1.6em;
}
.onerecent2:hover{
    background:#fcfcff;
}
.recent_button{
    margin:0 5px 0 0;
    padding:3px;
}
#renew_block{
    float:right;
}
#renew_button{
    float:left;
}
@media only screen and (max-width:520px) {
    #left_recent_col,#right_recent_col{
        min-width:100%;
        max-width:100%;
        margin-left:0%
    }
    .sub2 {
	   font-weight: 500;
	   padding:0px;
}
</style>

<div id="my_recent_topics2">
    <div class="sub1">
	    <div id="my_btn_recent"></div>
		<div class="sub2">Последние сообщения</div>
	</div>
	<div id="my_recent">
		<div id="left_recent_col"></div>
		<div id="right_recent_col"></div>
	</div>
	<div style="clear:both;padding-bottom: 25px;">
	    <input id="renew_button" type="button" value="Обновить">
	    <div id="renew_block">
		    <input id="renew" value="1" type="checkbox"/> Обновлять автоматически
	    </div>
    </div>
</div>';