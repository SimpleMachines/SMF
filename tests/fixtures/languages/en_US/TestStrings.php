<?php

// A language directory for LangTest. Only en_US ships with SMF, so there is no
// second language on disk to ask for, and nothing exercises the choice between
// one language and another without these.
//
// en_US defines all three strings, es_ES defines two of them and de_DE defines
// one, so which file a string ends up coming from says how far down the chain
// [asked for, forum default, English] Lang::load() had to go to find it.

$txt['test_in_all_three'] = 'English';
$txt['test_not_in_german'] = 'English';
$txt['test_english_only'] = 'English';
