<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Helper function for utf8_sanitize_invisibles.
 *
 * Character class lists compiled from:
 * https://unicode.org/Public/UNIDATA/DerivedCoreProperties.txt
 * https://unicode.org/Public/UNIDATA/PropList.txt
 * https://unicode.org/Public/UNIDATA/emoji/emoji-data.txt
 * https://unicode.org/Public/UNIDATA/extracted/DerivedGeneralCategory.txt
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Character classes for various Unicode properties.
 */
function utf8_regex_properties()
{
	return array(
		'Bidi_Control' => '\x{061C}\x{200E}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}',
		'Cn' => '\x{0378}-\x{0379}\x{0380}-\x{0383}\x{038B}\x{038D}\x{03A2}\x{0530}\x{0557}-\x{0558}\x{058B}-\x{058C}\x{0590}\x{05C8}-\x{05CF}\x{05EB}-\x{05EE}\x{05F5}-\x{05FF}\x{070E}\x{074B}-\x{074C}\x{07B2}-\x{07BF}\x{07FB}-\x{07FC}\x{082E}-\x{082F}\x{083F}\x{085C}-\x{085D}\x{085F}\x{086B}-\x{086F}\x{088F}\x{0892}-\x{0897}\x{0984}\x{098D}-\x{098E}\x{0991}-\x{0992}\x{09A9}\x{09B1}\x{09B3}-\x{09B5}\x{09BA}-\x{09BB}\x{09C5}-\x{09C6}\x{09C9}-\x{09CA}\x{09CF}-\x{09D6}\x{09D8}-\x{09DB}\x{09DE}\x{09E4}-\x{09E5}\x{09FF}-\x{0A00}\x{0A04}\x{0A0B}-\x{0A0E}\x{0A11}-\x{0A12}\x{0A29}\x{0A31}\x{0A34}\x{0A37}\x{0A3A}-\x{0A3B}\x{0A3D}\x{0A43}-\x{0A46}\x{0A49}-\x{0A4A}\x{0A4E}-\x{0A50}\x{0A52}-\x{0A58}\x{0A5D}\x{0A5F}-\x{0A65}\x{0A77}-\x{0A80}\x{0A84}\x{0A8E}\x{0A92}\x{0AA9}\x{0AB1}\x{0AB4}\x{0ABA}-\x{0ABB}\x{0AC6}\x{0ACA}\x{0ACE}-\x{0ACF}\x{0AD1}-\x{0ADF}\x{0AE4}-\x{0AE5}\x{0AF2}-\x{0AF8}\x{0B00}\x{0B04}\x{0B0D}-\x{0B0E}\x{0B11}-\x{0B12}\x{0B29}\x{0B31}\x{0B34}\x{0B3A}-\x{0B3B}\x{0B45}-\x{0B46}\x{0B49}-\x{0B4A}\x{0B4E}-\x{0B54}\x{0B58}-\x{0B5B}\x{0B5E}\x{0B64}-\x{0B65}\x{0B78}-\x{0B81}\x{0B84}\x{0B8B}-\x{0B8D}\x{0B91}\x{0B96}-\x{0B98}\x{0B9B}\x{0B9D}\x{0BA0}-\x{0BA2}\x{0BA5}-\x{0BA7}\x{0BAB}-\x{0BAD}\x{0BBA}-\x{0BBD}\x{0BC3}-\x{0BC5}\x{0BC9}\x{0BCE}-\x{0BCF}\x{0BD1}-\x{0BD6}\x{0BD8}-\x{0BE5}\x{0BFB}-\x{0BFF}\x{0C0D}\x{0C11}\x{0C29}\x{0C3A}-\x{0C3B}\x{0C45}\x{0C49}\x{0C4E}-\x{0C54}\x{0C57}\x{0C5B}-\x{0C5C}\x{0C5E}-\x{0C5F}\x{0C64}-\x{0C65}\x{0C70}-\x{0C76}\x{0C8D}\x{0C91}\x{0CA9}\x{0CB4}\x{0CBA}-\x{0CBB}\x{0CC5}\x{0CC9}\x{0CCE}-\x{0CD4}\x{0CD7}-\x{0CDC}\x{0CDF}\x{0CE4}-\x{0CE5}\x{0CF0}\x{0CF3}-\x{0CFF}\x{0D0D}\x{0D11}\x{0D45}\x{0D49}\x{0D50}-\x{0D53}\x{0D64}-\x{0D65}\x{0D80}\x{0D84}\x{0D97}-\x{0D99}\x{0DB2}\x{0DBC}\x{0DBE}-\x{0DBF}\x{0DC7}-\x{0DC9}\x{0DCB}-\x{0DCE}\x{0DD5}\x{0DD7}\x{0DE0}-\x{0DE5}\x{0DF0}-\x{0DF1}\x{0DF5}-\x{0E00}\x{0E3B}-\x{0E3E}\x{0E5C}-\x{0E80}\x{0E83}\x{0E85}\x{0E8B}\x{0EA4}\x{0EA6}\x{0EBE}-\x{0EBF}\x{0EC5}\x{0EC7}\x{0ECE}-\x{0ECF}\x{0EDA}-\x{0EDB}\x{0EE0}-\x{0EFF}\x{0F48}\x{0F6D}-\x{0F70}\x{0F98}\x{0FBD}\x{0FCD}\x{0FDB}-\x{0FFF}\x{10C6}\x{10C8}-\x{10CC}\x{10CE}-\x{10CF}\x{1249}\x{124E}-\x{124F}\x{1257}\x{1259}\x{125E}-\x{125F}\x{1289}\x{128E}-\x{128F}\x{12B1}\x{12B6}-\x{12B7}\x{12BF}\x{12C1}\x{12C6}-\x{12C7}\x{12D7}\x{1311}\x{1316}-\x{1317}\x{135B}-\x{135C}\x{137D}-\x{137F}\x{139A}-\x{139F}\x{13F6}-\x{13F7}\x{13FE}-\x{13FF}\x{169D}-\x{169F}\x{16F9}-\x{16FF}\x{1716}-\x{171E}\x{1737}-\x{173F}\x{1754}-\x{175F}\x{176D}\x{1771}\x{1774}-\x{177F}\x{17DE}-\x{17DF}\x{17EA}-\x{17EF}\x{17FA}-\x{17FF}\x{181A}-\x{181F}\x{1879}-\x{187F}\x{18AB}-\x{18AF}\x{18F6}-\x{18FF}\x{191F}\x{192C}-\x{192F}\x{193C}-\x{193F}\x{1941}-\x{1943}\x{196E}-\x{196F}\x{1975}-\x{197F}\x{19AC}-\x{19AF}\x{19CA}-\x{19CF}\x{19DB}-\x{19DD}\x{1A1C}-\x{1A1D}\x{1A5F}\x{1A7D}-\x{1A7E}\x{1A8A}-\x{1A8F}\x{1A9A}-\x{1A9F}\x{1AAE}-\x{1AAF}\x{1ACF}-\x{1AFF}\x{1B4D}-\x{1B4F}\x{1B7F}\x{1BF4}-\x{1BFB}\x{1C38}-\x{1C3A}\x{1C4A}-\x{1C4C}\x{1C89}-\x{1C8F}\x{1CBB}-\x{1CBC}\x{1CC8}-\x{1CCF}\x{1CFB}-\x{1CFF}\x{1F16}-\x{1F17}\x{1F1E}-\x{1F1F}\x{1F46}-\x{1F47}\x{1F4E}-\x{1F4F}\x{1F58}\x{1F5A}\x{1F5C}\x{1F5E}\x{1F7E}-\x{1F7F}\x{1FB5}\x{1FC5}\x{1FD4}-\x{1FD5}\x{1FDC}\x{1FF0}-\x{1FF1}\x{1FF5}\x{1FFF}\x{2065}\x{2072}-\x{2073}\x{208F}\x{209D}-\x{209F}\x{20C1}-\x{20CF}\x{20F1}-\x{20FF}\x{218C}-\x{218F}\x{2427}-\x{243F}\x{244B}-\x{245F}\x{2B74}-\x{2B75}\x{2B96}\x{2CF4}-\x{2CF8}\x{2D26}\x{2D28}-\x{2D2C}\x{2D2E}-\x{2D2F}\x{2D68}-\x{2D6E}\x{2D71}-\x{2D7E}\x{2D97}-\x{2D9F}\x{2DA7}\x{2DAF}\x{2DB7}\x{2DBF}\x{2DC7}\x{2DCF}\x{2DD7}\x{2DDF}\x{2E5E}-\x{2E7F}\x{2E9A}\x{2EF4}-\x{2EFF}\x{2FD6}-\x{2FEF}\x{2FFC}-\x{2FFF}\x{3040}\x{3097}-\x{3098}\x{3100}-\x{3104}\x{3130}\x{318F}\x{31E4}-\x{31EF}\x{321F}\x{A48D}-\x{A48F}\x{A4C7}-\x{A4CF}\x{A62C}-\x{A63F}\x{A6F8}-\x{A6FF}\x{A7CB}-\x{A7CF}\x{A7D2}\x{A7D4}\x{A7DA}-\x{A7F1}\x{A82D}-\x{A82F}\x{A83A}-\x{A83F}\x{A878}-\x{A87F}\x{A8C6}-\x{A8CD}\x{A8DA}-\x{A8DF}\x{A954}-\x{A95E}\x{A97D}-\x{A97F}\x{A9CE}\x{A9DA}-\x{A9DD}\x{A9FF}\x{AA37}-\x{AA3F}\x{AA4E}-\x{AA4F}\x{AA5A}-\x{AA5B}\x{AAC3}-\x{AADA}\x{AAF7}-\x{AB00}\x{AB07}-\x{AB08}\x{AB0F}-\x{AB10}\x{AB17}-\x{AB1F}\x{AB27}\x{AB2F}\x{AB6C}-\x{AB6F}\x{ABEE}-\x{ABEF}\x{ABFA}-\x{ABFF}\x{D7A4}-\x{D7AF}\x{D7C7}-\x{D7CA}\x{D7FC}-\x{D7FF}\x{FA6E}-\x{FA6F}\x{FADA}-\x{FAFF}\x{FB07}-\x{FB12}\x{FB18}-\x{FB1C}\x{FB37}\x{FB3D}\x{FB3F}\x{FB42}\x{FB45}\x{FBC3}-\x{FBD2}\x{FD90}-\x{FD91}\x{FDC8}-\x{FDCE}\x{FDD0}-\x{FDEF}\x{FE1A}-\x{FE1F}\x{FE53}\x{FE67}\x{FE6C}-\x{FE6F}\x{FE75}\x{FEFD}-\x{FEFE}\x{FF00}\x{FFBF}-\x{FFC1}\x{FFC8}-\x{FFC9}\x{FFD0}-\x{FFD1}\x{FFD8}-\x{FFD9}\x{FFDD}-\x{FFDF}\x{FFE7}\x{FFEF}-\x{FFF8}\x{FFFE}-\x{FFFF}\x{1000C}\x{10027}\x{1003B}\x{1003E}\x{1004E}-\x{1004F}\x{1005E}-\x{1007F}\x{100FB}-\x{100FF}\x{10103}-\x{10106}\x{10134}-\x{10136}\x{1018F}\x{1019D}-\x{1019F}\x{101A1}-\x{101CF}\x{101FE}-\x{1027F}\x{1029D}-\x{1029F}\x{102D1}-\x{102DF}\x{102FC}-\x{102FF}\x{10324}-\x{1032C}\x{1034B}-\x{1034F}\x{1037B}-\x{1037F}\x{1039E}\x{103C4}-\x{103C7}\x{103D6}-\x{103FF}\x{1049E}-\x{1049F}\x{104AA}-\x{104AF}\x{104D4}-\x{104D7}\x{104FC}-\x{104FF}\x{10528}-\x{1052F}\x{10564}-\x{1056E}\x{1057B}\x{1058B}\x{10593}\x{10596}\x{105A2}\x{105B2}\x{105BA}\x{105BD}-\x{105FF}\x{10737}-\x{1073F}\x{10756}-\x{1075F}\x{10768}-\x{1077F}\x{10786}\x{107B1}\x{107BB}-\x{107FF}\x{10806}-\x{10807}\x{10809}\x{10836}\x{10839}-\x{1083B}\x{1083D}-\x{1083E}\x{10856}\x{1089F}-\x{108A6}\x{108B0}-\x{108DF}\x{108F3}\x{108F6}-\x{108FA}\x{1091C}-\x{1091E}\x{1093A}-\x{1093E}\x{10940}-\x{1097F}\x{109B8}-\x{109BB}\x{109D0}-\x{109D1}\x{10A04}\x{10A07}-\x{10A0B}\x{10A14}\x{10A18}\x{10A36}-\x{10A37}\x{10A3B}-\x{10A3E}\x{10A49}-\x{10A4F}\x{10A59}-\x{10A5F}\x{10AA0}-\x{10ABF}\x{10AE7}-\x{10AEA}\x{10AF7}-\x{10AFF}\x{10B36}-\x{10B38}\x{10B56}-\x{10B57}\x{10B73}-\x{10B77}\x{10B92}-\x{10B98}\x{10B9D}-\x{10BA8}\x{10BB0}-\x{10BFF}\x{10C49}-\x{10C7F}\x{10CB3}-\x{10CBF}\x{10CF3}-\x{10CF9}\x{10D28}-\x{10D2F}\x{10D3A}-\x{10E5F}\x{10E7F}\x{10EAA}\x{10EAE}-\x{10EAF}\x{10EB2}-\x{10EFF}\x{10F28}-\x{10F2F}\x{10F5A}-\x{10F6F}\x{10F8A}-\x{10FAF}\x{10FCC}-\x{10FDF}\x{10FF7}-\x{10FFF}\x{1104E}-\x{11051}\x{11076}-\x{1107E}\x{110C3}-\x{110CC}\x{110CE}-\x{110CF}\x{110E9}-\x{110EF}\x{110FA}-\x{110FF}\x{11135}\x{11148}-\x{1114F}\x{11177}-\x{1117F}\x{111E0}\x{111F5}-\x{111FF}\x{11212}\x{1123F}-\x{1127F}\x{11287}\x{11289}\x{1128E}\x{1129E}\x{112AA}-\x{112AF}\x{112EB}-\x{112EF}\x{112FA}-\x{112FF}\x{11304}\x{1130D}-\x{1130E}\x{11311}-\x{11312}\x{11329}\x{11331}\x{11334}\x{1133A}\x{11345}-\x{11346}\x{11349}-\x{1134A}\x{1134E}-\x{1134F}\x{11351}-\x{11356}\x{11358}-\x{1135C}\x{11364}-\x{11365}\x{1136D}-\x{1136F}\x{11375}-\x{113FF}\x{1145C}\x{11462}-\x{1147F}\x{114C8}-\x{114CF}\x{114DA}-\x{1157F}\x{115B6}-\x{115B7}\x{115DE}-\x{115FF}\x{11645}-\x{1164F}\x{1165A}-\x{1165F}\x{1166D}-\x{1167F}\x{116BA}-\x{116BF}\x{116CA}-\x{116FF}\x{1171B}-\x{1171C}\x{1172C}-\x{1172F}\x{11747}-\x{117FF}\x{1183C}-\x{1189F}\x{118F3}-\x{118FE}\x{11907}-\x{11908}\x{1190A}-\x{1190B}\x{11914}\x{11917}\x{11936}\x{11939}-\x{1193A}\x{11947}-\x{1194F}\x{1195A}-\x{1199F}\x{119A8}-\x{119A9}\x{119D8}-\x{119D9}\x{119E5}-\x{119FF}\x{11A48}-\x{11A4F}\x{11AA3}-\x{11AAF}\x{11AF9}-\x{11BFF}\x{11C09}\x{11C37}\x{11C46}-\x{11C4F}\x{11C6D}-\x{11C6F}\x{11C90}-\x{11C91}\x{11CA8}\x{11CB7}-\x{11CFF}\x{11D07}\x{11D0A}\x{11D37}-\x{11D39}\x{11D3B}\x{11D3E}\x{11D48}-\x{11D4F}\x{11D5A}-\x{11D5F}\x{11D66}\x{11D69}\x{11D8F}\x{11D92}\x{11D99}-\x{11D9F}\x{11DAA}-\x{11EDF}\x{11EF9}-\x{11FAF}\x{11FB1}-\x{11FBF}\x{11FF2}-\x{11FFE}\x{1239A}-\x{123FF}\x{1246F}\x{12475}-\x{1247F}\x{12544}-\x{12F8F}\x{12FF3}-\x{12FFF}\x{1342F}\x{13439}-\x{143FF}\x{14647}-\x{167FF}\x{16A39}-\x{16A3F}\x{16A5F}\x{16A6A}-\x{16A6D}\x{16ABF}\x{16ACA}-\x{16ACF}\x{16AEE}-\x{16AEF}\x{16AF6}-\x{16AFF}\x{16B46}-\x{16B4F}\x{16B5A}\x{16B62}\x{16B78}-\x{16B7C}\x{16B90}-\x{16E3F}\x{16E9B}-\x{16EFF}\x{16F4B}-\x{16F4E}\x{16F88}-\x{16F8E}\x{16FA0}-\x{16FDF}\x{16FE5}-\x{16FEF}\x{16FF2}-\x{16FFF}\x{187F8}-\x{187FF}\x{18CD6}-\x{18CFF}\x{18D09}-\x{1AFEF}\x{1AFF4}\x{1AFFC}\x{1AFFF}\x{1B123}-\x{1B14F}\x{1B153}-\x{1B163}\x{1B168}-\x{1B16F}\x{1B2FC}-\x{1BBFF}\x{1BC6B}-\x{1BC6F}\x{1BC7D}-\x{1BC7F}\x{1BC89}-\x{1BC8F}\x{1BC9A}-\x{1BC9B}\x{1BCA4}-\x{1CEFF}\x{1CF2E}-\x{1CF2F}\x{1CF47}-\x{1CF4F}\x{1CFC4}-\x{1CFFF}\x{1D0F6}-\x{1D0FF}\x{1D127}-\x{1D128}\x{1D1EB}-\x{1D1FF}\x{1D246}-\x{1D2DF}\x{1D2F4}-\x{1D2FF}\x{1D357}-\x{1D35F}\x{1D379}-\x{1D3FF}\x{1D455}\x{1D49D}\x{1D4A0}-\x{1D4A1}\x{1D4A3}-\x{1D4A4}\x{1D4A7}-\x{1D4A8}\x{1D4AD}\x{1D4BA}\x{1D4BC}\x{1D4C4}\x{1D506}\x{1D50B}-\x{1D50C}\x{1D515}\x{1D51D}\x{1D53A}\x{1D53F}\x{1D545}\x{1D547}-\x{1D549}\x{1D551}\x{1D6A6}-\x{1D6A7}\x{1D7CC}-\x{1D7CD}\x{1DA8C}-\x{1DA9A}\x{1DAA0}\x{1DAB0}-\x{1DEFF}\x{1DF1F}-\x{1DFFF}\x{1E007}\x{1E019}-\x{1E01A}\x{1E022}\x{1E025}\x{1E02B}-\x{1E0FF}\x{1E12D}-\x{1E12F}\x{1E13E}-\x{1E13F}\x{1E14A}-\x{1E14D}\x{1E150}-\x{1E28F}\x{1E2AF}-\x{1E2BF}\x{1E2FA}-\x{1E2FE}\x{1E300}-\x{1E7DF}\x{1E7E7}\x{1E7EC}\x{1E7EF}\x{1E7FF}\x{1E8C5}-\x{1E8C6}\x{1E8D7}-\x{1E8FF}\x{1E94C}-\x{1E94F}\x{1E95A}-\x{1E95D}\x{1E960}-\x{1EC70}\x{1ECB5}-\x{1ED00}\x{1ED3E}-\x{1EDFF}\x{1EE04}\x{1EE20}\x{1EE23}\x{1EE25}-\x{1EE26}\x{1EE28}\x{1EE33}\x{1EE38}\x{1EE3A}\x{1EE3C}-\x{1EE41}\x{1EE43}-\x{1EE46}\x{1EE48}\x{1EE4A}\x{1EE4C}\x{1EE50}\x{1EE53}\x{1EE55}-\x{1EE56}\x{1EE58}\x{1EE5A}\x{1EE5C}\x{1EE5E}\x{1EE60}\x{1EE63}\x{1EE65}-\x{1EE66}\x{1EE6B}\x{1EE73}\x{1EE78}\x{1EE7D}\x{1EE7F}\x{1EE8A}\x{1EE9C}-\x{1EEA0}\x{1EEA4}\x{1EEAA}\x{1EEBC}-\x{1EEEF}\x{1EEF2}-\x{1EFFF}\x{1F02C}-\x{1F02F}\x{1F094}-\x{1F09F}\x{1F0AF}-\x{1F0B0}\x{1F0C0}\x{1F0D0}\x{1F0F6}-\x{1F0FF}\x{1F1AE}-\x{1F1E5}\x{1F203}-\x{1F20F}\x{1F23C}-\x{1F23F}\x{1F249}-\x{1F24F}\x{1F252}-\x{1F25F}\x{1F266}-\x{1F2FF}\x{1F6D8}-\x{1F6DC}\x{1F6ED}-\x{1F6EF}\x{1F6FD}-\x{1F6FF}\x{1F774}-\x{1F77F}\x{1F7D9}-\x{1F7DF}\x{1F7EC}-\x{1F7EF}\x{1F7F1}-\x{1F7FF}\x{1F80C}-\x{1F80F}\x{1F848}-\x{1F84F}\x{1F85A}-\x{1F85F}\x{1F888}-\x{1F88F}\x{1F8AE}-\x{1F8AF}\x{1F8B2}-\x{1F8FF}\x{1FA54}-\x{1FA5F}\x{1FA6E}-\x{1FA6F}\x{1FA75}-\x{1FA77}\x{1FA7D}-\x{1FA7F}\x{1FA87}-\x{1FA8F}\x{1FAAD}-\x{1FAAF}\x{1FABB}-\x{1FABF}\x{1FAC6}-\x{1FACF}\x{1FADA}-\x{1FADF}\x{1FAE8}-\x{1FAEF}\x{1FAF7}-\x{1FAFF}\x{1FB93}\x{1FBCB}-\x{1FBEF}\x{1FBFA}-\x{1FFFF}\x{2A6E0}-\x{2A6FF}\x{2B739}-\x{2B73F}\x{2B81E}-\x{2B81F}\x{2CEA2}-\x{2CEAF}\x{2EBE1}-\x{2F7FF}\x{2FA1E}-\x{2FFFF}\x{3134B}-\x{E0000}\x{E0002}-\x{E001F}\x{E0080}-\x{E00FF}\x{E01F0}-\x{EFFFF}\x{FFFFE}-\x{FFFFF}\x{10FFFE}-\x{10FFFF}',
		'Default_Ignorable_Code_Point' => '\x{00AD}\x{034F}\x{061C}\x{115F}-\x{1160}\x{17B4}-\x{17B5}\x{180B}-\x{180D}\x{180E}\x{180F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2065}\x{2066}-\x{206F}\x{3164}\x{FE00}-\x{FE0F}\x{FEFF}\x{FFA0}\x{FFF0}-\x{FFF8}\x{1BCA0}-\x{1BCA3}\x{1D173}-\x{1D17A}\x{E0000}\x{E0001}\x{E0002}-\x{E001F}\x{E0020}-\x{E007F}\x{E0080}-\x{E00FF}\x{E0100}-\x{E01EF}\x{E01F0}-\x{E0FFF}',
		'Emoji' => '\x{0023}\x{002A}\x{0030}-\x{0039}\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23EC}\x{23ED}-\x{23EE}\x{23EF}\x{23F0}\x{23F1}-\x{23F2}\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2601}\x{2602}-\x{2603}\x{2604}\x{260E}\x{2611}\x{2614}-\x{2615}\x{2618}\x{261D}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}\x{262F}\x{2638}-\x{2639}\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}\x{267F}\x{2692}\x{2693}\x{2694}\x{2695}\x{2696}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26A7}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CE}\x{26CF}\x{26D1}\x{26D3}\x{26D4}\x{26E9}\x{26EA}\x{26F0}-\x{26F1}\x{26F2}-\x{26F3}\x{26F4}\x{26F5}\x{26F7}-\x{26F9}\x{26FA}\x{26FD}\x{2702}\x{2705}\x{2708}-\x{270C}\x{270D}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2763}\x{2764}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F0CF}\x{1F170}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F1E6}-\x{1F1FF}\x{1F201}-\x{1F202}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F23A}\x{1F250}-\x{1F251}\x{1F300}-\x{1F30C}\x{1F30D}-\x{1F30E}\x{1F30F}\x{1F310}\x{1F311}\x{1F312}\x{1F313}-\x{1F315}\x{1F316}-\x{1F318}\x{1F319}\x{1F31A}\x{1F31B}\x{1F31C}\x{1F31D}-\x{1F31E}\x{1F31F}-\x{1F320}\x{1F321}\x{1F324}-\x{1F32C}\x{1F32D}-\x{1F32F}\x{1F330}-\x{1F331}\x{1F332}-\x{1F333}\x{1F334}-\x{1F335}\x{1F336}\x{1F337}-\x{1F34A}\x{1F34B}\x{1F34C}-\x{1F34F}\x{1F350}\x{1F351}-\x{1F37B}\x{1F37C}\x{1F37D}\x{1F37E}-\x{1F37F}\x{1F380}-\x{1F393}\x{1F396}-\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}-\x{1F39F}\x{1F3A0}-\x{1F3C4}\x{1F3C5}\x{1F3C6}\x{1F3C7}\x{1F3C8}\x{1F3C9}\x{1F3CA}\x{1F3CB}-\x{1F3CE}\x{1F3CF}-\x{1F3D3}\x{1F3D4}-\x{1F3DF}\x{1F3E0}-\x{1F3E3}\x{1F3E4}\x{1F3E5}-\x{1F3F0}\x{1F3F3}\x{1F3F4}\x{1F3F5}\x{1F3F7}\x{1F3F8}-\x{1F407}\x{1F408}\x{1F409}-\x{1F40B}\x{1F40C}-\x{1F40E}\x{1F40F}-\x{1F410}\x{1F411}-\x{1F412}\x{1F413}\x{1F414}\x{1F415}\x{1F416}\x{1F417}-\x{1F429}\x{1F42A}\x{1F42B}-\x{1F43E}\x{1F43F}\x{1F440}\x{1F441}\x{1F442}-\x{1F464}\x{1F465}\x{1F466}-\x{1F46B}\x{1F46C}-\x{1F46D}\x{1F46E}-\x{1F4AC}\x{1F4AD}\x{1F4AE}-\x{1F4B5}\x{1F4B6}-\x{1F4B7}\x{1F4B8}-\x{1F4EB}\x{1F4EC}-\x{1F4ED}\x{1F4EE}\x{1F4EF}\x{1F4F0}-\x{1F4F4}\x{1F4F5}\x{1F4F6}-\x{1F4F7}\x{1F4F8}\x{1F4F9}-\x{1F4FC}\x{1F4FD}\x{1F4FF}-\x{1F502}\x{1F503}\x{1F504}-\x{1F507}\x{1F508}\x{1F509}\x{1F50A}-\x{1F514}\x{1F515}\x{1F516}-\x{1F52B}\x{1F52C}-\x{1F52D}\x{1F52E}-\x{1F53D}\x{1F549}-\x{1F54A}\x{1F54B}-\x{1F54E}\x{1F550}-\x{1F55B}\x{1F55C}-\x{1F567}\x{1F56F}-\x{1F570}\x{1F573}-\x{1F579}\x{1F57A}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F590}\x{1F595}-\x{1F596}\x{1F5A4}\x{1F5A5}\x{1F5A8}\x{1F5B1}-\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}\x{1F5FB}-\x{1F5FF}\x{1F600}\x{1F601}-\x{1F606}\x{1F607}-\x{1F608}\x{1F609}-\x{1F60D}\x{1F60E}\x{1F60F}\x{1F610}\x{1F611}\x{1F612}-\x{1F614}\x{1F615}\x{1F616}\x{1F617}\x{1F618}\x{1F619}\x{1F61A}\x{1F61B}\x{1F61C}-\x{1F61E}\x{1F61F}\x{1F620}-\x{1F625}\x{1F626}-\x{1F627}\x{1F628}-\x{1F62B}\x{1F62C}\x{1F62D}\x{1F62E}-\x{1F62F}\x{1F630}-\x{1F633}\x{1F634}\x{1F635}\x{1F636}\x{1F637}-\x{1F640}\x{1F641}-\x{1F644}\x{1F645}-\x{1F64F}\x{1F680}\x{1F681}-\x{1F682}\x{1F683}-\x{1F685}\x{1F686}\x{1F687}\x{1F688}\x{1F689}\x{1F68A}-\x{1F68B}\x{1F68C}\x{1F68D}\x{1F68E}\x{1F68F}\x{1F690}\x{1F691}-\x{1F693}\x{1F694}\x{1F695}\x{1F696}\x{1F697}\x{1F698}\x{1F699}-\x{1F69A}\x{1F69B}-\x{1F6A1}\x{1F6A2}\x{1F6A3}\x{1F6A4}-\x{1F6A5}\x{1F6A6}\x{1F6A7}-\x{1F6AD}\x{1F6AE}-\x{1F6B1}\x{1F6B2}\x{1F6B3}-\x{1F6B5}\x{1F6B6}\x{1F6B7}-\x{1F6B8}\x{1F6B9}-\x{1F6BE}\x{1F6BF}\x{1F6C0}\x{1F6C1}-\x{1F6C5}\x{1F6CB}\x{1F6CC}\x{1F6CD}-\x{1F6CF}\x{1F6D0}\x{1F6D1}-\x{1F6D2}\x{1F6D5}\x{1F6D6}-\x{1F6D7}\x{1F6DD}-\x{1F6DF}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6EB}-\x{1F6EC}\x{1F6F0}\x{1F6F3}\x{1F6F4}-\x{1F6F6}\x{1F6F7}-\x{1F6F8}\x{1F6F9}\x{1F6FA}\x{1F6FB}-\x{1F6FC}\x{1F7E0}-\x{1F7EB}\x{1F7F0}\x{1F90C}\x{1F90D}-\x{1F90F}\x{1F910}-\x{1F918}\x{1F919}-\x{1F91E}\x{1F91F}\x{1F920}-\x{1F927}\x{1F928}-\x{1F92F}\x{1F930}\x{1F931}-\x{1F932}\x{1F933}-\x{1F93A}\x{1F93C}-\x{1F93E}\x{1F93F}\x{1F940}-\x{1F945}\x{1F947}-\x{1F94B}\x{1F94C}\x{1F94D}-\x{1F94F}\x{1F950}-\x{1F95E}\x{1F95F}-\x{1F96B}\x{1F96C}-\x{1F970}\x{1F971}\x{1F972}\x{1F973}-\x{1F976}\x{1F977}-\x{1F978}\x{1F979}\x{1F97A}\x{1F97B}\x{1F97C}-\x{1F97F}\x{1F980}-\x{1F984}\x{1F985}-\x{1F991}\x{1F992}-\x{1F997}\x{1F998}-\x{1F9A2}\x{1F9A3}-\x{1F9A4}\x{1F9A5}-\x{1F9AA}\x{1F9AB}-\x{1F9AD}\x{1F9AE}-\x{1F9AF}\x{1F9B0}-\x{1F9B9}\x{1F9BA}-\x{1F9BF}\x{1F9C0}\x{1F9C1}-\x{1F9C2}\x{1F9C3}-\x{1F9CA}\x{1F9CB}\x{1F9CC}\x{1F9CD}-\x{1F9CF}\x{1F9D0}-\x{1F9E6}\x{1F9E7}-\x{1F9FF}\x{1FA70}-\x{1FA73}\x{1FA74}\x{1FA78}-\x{1FA7A}\x{1FA7B}-\x{1FA7C}\x{1FA80}-\x{1FA82}\x{1FA83}-\x{1FA86}\x{1FA90}-\x{1FA95}\x{1FA96}-\x{1FAA8}\x{1FAA9}-\x{1FAAC}\x{1FAB0}-\x{1FAB6}\x{1FAB7}-\x{1FABA}\x{1FAC0}-\x{1FAC2}\x{1FAC3}-\x{1FAC5}\x{1FAD0}-\x{1FAD6}\x{1FAD7}-\x{1FAD9}\x{1FAE0}-\x{1FAE7}\x{1FAF0}-\x{1FAF6}',
		'Emoji_Modifier' => '\x{1F3FB}-\x{1F3FF}',
		'Ideographic' => '\x{3006}\x{3007}\x{3021}-\x{3029}\x{3038}-\x{303A}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FA6D}\x{FA70}-\x{FAD9}\x{16FE4}\x{17000}-\x{187F7}\x{18800}-\x{18CD5}\x{18D00}-\x{18D08}\x{1B170}-\x{1B2FB}\x{20000}-\x{2A6DF}\x{2A700}-\x{2B738}\x{2B740}-\x{2B81D}\x{2B820}-\x{2CEA1}\x{2CEB0}-\x{2EBE0}\x{2F800}-\x{2FA1D}\x{30000}-\x{3134A}',
		'Join_Control' => '\x{200C}-\x{200D}',
		'Regional_Indicator' => '\x{1F1E6}-\x{1F1FF}',
		'Variation_Selector' => '\x{180B}-\x{180D}\x{180F}\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}',
	);
}

/**
 * Helper function for utf8_sanitize_invisibles.
 *
 * Character class lists compiled from:
 * https://unicode.org/Public/UNIDATA/StandardizedVariants.txt
 * https://unicode.org/Public/UNIDATA/emoji/emoji-variation-sequences.txt
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Character classes for filtering variation selectors.
 */
function utf8_regex_variation_selectors()
{
	return array(
		'\x{FE0E}\x{FE0F}' => '\x{0023}\x{002A}\x{0030}-\x{0039}\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23EA}\x{23ED}-\x{23EF}\x{23F1}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}-\x{2615}\x{2618}\x{261D}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}-\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}-\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}-\x{267F}\x{2692}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26A7}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CF}\x{26D1}\x{26D3}-\x{26D4}\x{26E9}-\x{26EA}\x{26F0}-\x{26F5}\x{26F7}-\x{26FA}\x{26FD}\x{2702}\x{2708}-\x{2709}\x{270C}-\x{270D}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2733}-\x{2734}\x{2744}\x{2747}\x{2753}\x{2757}\x{2763}-\x{2764}\x{27A1}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F170}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F202}\x{1F21A}\x{1F22F}\x{1F237}\x{1F30D}-\x{1F30F}\x{1F315}\x{1F31C}\x{1F321}\x{1F324}-\x{1F32C}\x{1F336}\x{1F378}\x{1F37D}\x{1F393}\x{1F396}-\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}-\x{1F39F}\x{1F3A7}\x{1F3AC}-\x{1F3AE}\x{1F3C2}\x{1F3C4}\x{1F3C6}\x{1F3CA}-\x{1F3CE}\x{1F3D4}-\x{1F3E0}\x{1F3ED}\x{1F3F3}\x{1F3F5}\x{1F3F7}\x{1F408}\x{1F415}\x{1F41F}\x{1F426}\x{1F43F}\x{1F441}-\x{1F442}\x{1F446}-\x{1F449}\x{1F44D}-\x{1F44E}\x{1F453}\x{1F46A}\x{1F47D}\x{1F4A3}\x{1F4B0}\x{1F4B3}\x{1F4BB}\x{1F4BF}\x{1F4CB}\x{1F4DA}\x{1F4DF}\x{1F4E4}-\x{1F4E6}\x{1F4EA}-\x{1F4ED}\x{1F4F7}\x{1F4F9}-\x{1F4FB}\x{1F4FD}\x{1F508}\x{1F50D}\x{1F512}-\x{1F513}\x{1F549}-\x{1F54A}\x{1F550}-\x{1F567}\x{1F56F}-\x{1F570}\x{1F573}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F590}\x{1F5A5}\x{1F5A8}\x{1F5B1}-\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}\x{1F610}\x{1F687}\x{1F68D}\x{1F691}\x{1F694}\x{1F698}\x{1F6AD}\x{1F6B2}\x{1F6B9}-\x{1F6BA}\x{1F6BC}\x{1F6CB}\x{1F6CD}-\x{1F6CF}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6F0}\x{1F6F3}',
		'\x{FE02}' => '\x{537F}\x{5BE7}\x{618E}\x{61F2}\x{6717}\x{6A02}\x{6BBA}\x{6D41}\x{7DF4}\x{8005}\x{980B}\x{9F9C}',
		'\x{FE01}' => '\x{1D49C}\x{212C}\x{1D49E}-\x{1D49F}\x{2130}-\x{2131}\x{1D4A2}\x{210B}\x{2110}\x{1D4A5}-\x{1D4A6}\x{2112}\x{2133}\x{1D4A9}-\x{1D4AC}\x{211B}\x{1D4AE}-\x{1D4B5}\x{3001}-\x{3002}\x{FF01}\x{FF0C}\x{FF0E}\x{FF1A}-\x{FF1B}\x{FF1F}\x{3B9D}\x{3EB8}\x{4039}\x{4FAE}\x{50E7}\x{514D}\x{51B5}\x{5207}\x{52C7}\x{52C9}\x{52E4}\x{52FA}\x{5317}\x{5351}\x{537F}\x{5584}\x{5599}\x{559D}\x{5606}\x{585A}\x{5B3E}\x{5BE7}\x{5C6E}\x{5ECA}\x{5F22}\x{6094}\x{614C}\x{614E}\x{618E}\x{61F2}\x{61F6}\x{654F}\x{6674}\x{6691}\x{6717}\x{671B}\x{6885}\x{6A02}\x{6BBA}\x{6D41}\x{6D77}\x{6ECB}\x{6F22}\x{701E}\x{716E}\x{7235}\x{732A}\x{7387}\x{7471}\x{7570}\x{76CA}\x{76F4}\x{771F}\x{774A}\x{788C}\x{78CC}\x{7956}\x{798F}\x{7A40}\x{7BC0}\x{7DF4}\x{8005}\x{8201}\x{8279}\x{82E5}\x{8457}\x{865C}\x{8779}\x{8996}\x{8AAA}\x{8AED}\x{8AF8}\x{8AFE}\x{8B01}\x{8B39}\x{8B8A}\x{8D08}\x{8F38}\x{9038}\x{96E3}\x{9756}\x{97FF}\x{980B}\x{983B}\x{9B12}\x{9F9C}\x{22331}\x{25AA7}',
		'\x{FE00}' => '\x{0030}\x{2205}\x{2229}-\x{222A}\x{2268}-\x{2269}\x{2272}-\x{2273}\x{228A}-\x{228B}\x{2293}-\x{2295}\x{2297}\x{229C}\x{22DA}-\x{22DB}\x{2A3C}-\x{2A3D}\x{2A9D}-\x{2A9E}\x{2AAC}-\x{2AAD}\x{2ACB}-\x{2ACC}\x{FF10}\x{1D49C}\x{212C}\x{1D49E}-\x{1D49F}\x{2130}-\x{2131}\x{1D4A2}\x{210B}\x{2110}\x{1D4A5}-\x{1D4A6}\x{2112}\x{2133}\x{1D4A9}-\x{1D4AC}\x{211B}\x{1D4AE}-\x{1D4B5}\x{3001}-\x{3002}\x{FF01}\x{FF0C}\x{FF0E}\x{FF1A}-\x{FF1B}\x{FF1F}\x{1000}\x{1002}\x{1004}\x{1010}-\x{1011}\x{1015}\x{1019}-\x{101A}\x{101C}-\x{101D}\x{1022}\x{1031}\x{1075}\x{1078}\x{107A}\x{1080}\x{AA60}-\x{AA66}\x{AA6B}-\x{AA6C}\x{AA6F}\x{AA7A}\x{A856}\x{A85C}\x{A85E}-\x{A860}\x{A868}\x{10AC5}-\x{10AC6}\x{10AD6}-\x{10AD7}\x{10AE1}\x{349E}\x{34B9}\x{34BB}\x{34DF}\x{3515}\x{36EE}\x{36FC}\x{3781}\x{382F}\x{3862}\x{387C}\x{38C7}\x{38E3}\x{391C}\x{393A}\x{3A2E}\x{3A6C}\x{3AE4}\x{3B08}\x{3B19}\x{3B49}\x{3B9D}\x{3C18}\x{3C4E}\x{3D33}\x{3D96}\x{3EAC}\x{3EB8}\x{3F1B}\x{3FFC}\x{4008}\x{4018}\x{4039}\x{4046}\x{4096}\x{40E3}\x{412F}\x{4202}\x{4227}\x{42A0}\x{4301}\x{4334}\x{4359}\x{43D5}\x{43D9}\x{440B}\x{446B}\x{452B}\x{455D}\x{4561}\x{456B}\x{45D7}\x{45F9}\x{4635}\x{46BE}\x{46C7}\x{4995}\x{49E6}\x{4A6E}\x{4A76}\x{4AB2}\x{4B33}\x{4BCE}\x{4CCE}\x{4CED}\x{4CF8}\x{4D56}\x{4E0D}\x{4E26}\x{4E32}\x{4E38}-\x{4E39}\x{4E3D}\x{4E41}\x{4E82}\x{4E86}\x{4EAE}\x{4EC0}\x{4ECC}\x{4EE4}\x{4F60}\x{4F80}\x{4F86}\x{4F8B}\x{4FAE}\x{4FBB}\x{4FBF}\x{5002}\x{502B}\x{507A}\x{5099}\x{50CF}\x{50DA}\x{50E7}\x{5140}\x{5145}\x{514D}\x{5154}\x{5164}\x{5167}-\x{5169}\x{516D}\x{5177}\x{5180}\x{518D}\x{5192}\x{5195}\x{5197}\x{51A4}\x{51AC}\x{51B5}\x{51B7}\x{51C9}\x{51CC}\x{51DC}\x{51DE}\x{51F5}\x{5203}\x{5207}\x{5217}\x{5229}\x{523A}-\x{523B}\x{5246}\x{5272}\x{5277}\x{5289}\x{529B}\x{52A3}\x{52B3}\x{52C7}\x{52C9}\x{52D2}\x{52DE}\x{52E4}\x{52F5}\x{52FA}\x{5305}-\x{5306}\x{5317}\x{533F}\x{5349}\x{5351}\x{535A}\x{5373}\x{5375}\x{537D}\x{537F}\x{53C3}\x{53CA}\x{53DF}\x{53E5}\x{53EB}\x{53F1}\x{5406}\x{540F}\x{541D}\x{5438}\x{5442}\x{5448}\x{5468}\x{549E}\x{54A2}\x{54BD}\x{54F6}\x{5510}\x{5553}\x{5555}\x{5563}\x{5584}\x{5587}\x{5599}\x{559D}\x{55AB}\x{55B3}\x{55C0}\x{55C2}\x{55E2}\x{5606}\x{5651}\x{5668}\x{5674}\x{56F9}\x{5716}-\x{5717}\x{578B}\x{57CE}\x{57F4}\x{580D}\x{5831}-\x{5832}\x{5840}\x{585A}\x{585E}\x{58A8}\x{58AC}\x{58B3}\x{58D8}\x{58DF}\x{58EE}\x{58F2}\x{58F7}\x{5906}\x{591A}\x{5922}\x{5944}\x{5948}\x{5951}\x{5954}\x{5962}\x{5973}\x{59D8}\x{59EC}\x{5A1B}\x{5A27}\x{5A62}\x{5A66}\x{5AB5}\x{5B08}\x{5B28}\x{5B3E}\x{5B85}\x{5BC3}\x{5BD8}\x{5BE7}\x{5BEE}\x{5BF3}\x{5BFF}\x{5C06}\x{5C22}\x{5C3F}\x{5C60}\x{5C62}\x{5C64}-\x{5C65}\x{5C6E}\x{5C8D}\x{5CC0}\x{5D19}\x{5D43}\x{5D50}\x{5D6B}\x{5D6E}\x{5D7C}\x{5DB2}\x{5DBA}\x{5DE1}-\x{5DE2}\x{5DFD}\x{5E28}\x{5E3D}\x{5E69}\x{5E74}\x{5EA6}\x{5EB0}\x{5EB3}\x{5EB6}\x{5EC9}-\x{5ECA}\x{5ED2}-\x{5ED3}\x{5ED9}\x{5EEC}\x{5EFE}\x{5F04}\x{5F22}\x{5F53}\x{5F62}\x{5F69}\x{5F6B}\x{5F8B}\x{5F9A}\x{5FA9}\x{5FAD}\x{5FCD}\x{5FD7}\x{5FF5}\x{5FF9}\x{6012}\x{601C}\x{6075}\x{6081}\x{6094}\x{60C7}\x{60D8}\x{60E1}\x{6108}\x{6144}\x{6148}\x{614C}\x{614E}\x{6160}\x{6168}\x{617A}\x{618E}\x{6190}\x{61A4}\x{61AF}\x{61B2}\x{61DE}\x{61F2}\x{61F6}\x{6200}\x{6210}\x{621B}\x{622E}\x{6234}\x{625D}\x{62B1}\x{62C9}\x{62CF}\x{62D3}-\x{62D4}\x{62FC}\x{62FE}\x{633D}\x{6350}\x{6368}\x{637B}\x{6383}\x{63A0}\x{63A9}\x{63C4}-\x{63C5}\x{63E4}\x{641C}\x{6422}\x{6452}\x{6469}\x{6477}\x{647E}\x{649A}\x{649D}\x{64C4}\x{654F}\x{6556}\x{656C}\x{6578}\x{6599}\x{65C5}\x{65E2}-\x{65E3}\x{6613}\x{6649}\x{6674}\x{6688}\x{6691}\x{669C}\x{66B4}\x{66C6}\x{66F4}\x{66F8}\x{6700}\x{6717}\x{671B}\x{6721}\x{674E}\x{6753}\x{6756}\x{675E}\x{677B}\x{6785}\x{6797}\x{67F3}\x{67FA}\x{6817}\x{681F}\x{6852}\x{6881}\x{6885}\x{688E}\x{68A8}\x{6914}\x{6942}\x{69A3}\x{69EA}\x{6A02}\x{6A13}\x{6AA8}\x{6AD3}\x{6ADB}\x{6B04}\x{6B21}\x{6B54}\x{6B72}\x{6B77}\x{6B79}\x{6B9F}\x{6BAE}\x{6BBA}-\x{6BBB}\x{6C4E}\x{6C67}\x{6C88}\x{6CBF}\x{6CCC}-\x{6CCD}\x{6CE5}\x{6D16}\x{6D1B}\x{6D1E}\x{6D34}\x{6D3E}\x{6D41}\x{6D69}-\x{6D6A}\x{6D77}-\x{6D78}\x{6D85}\x{6DCB}\x{6DDA}\x{6DEA}\x{6DF9}\x{6E1A}\x{6E2F}\x{6E6E}\x{6E9C}\x{6EBA}\x{6EC7}\x{6ECB}\x{6ED1}\x{6EDB}\x{6F0F}\x{6F22}-\x{6F23}\x{6F6E}\x{6FC6}\x{6FEB}\x{6FFE}\x{701B}\x{701E}\x{7039}\x{704A}\x{7070}\x{7077}\x{707D}\x{7099}\x{70AD}\x{70C8}\x{70D9}\x{7145}\x{7149}\x{716E}\x{719C}\x{71CE}\x{71D0}\x{7210}\x{721B}\x{7228}\x{722B}\x{7235}\x{7250}\x{7262}\x{7280}\x{7295}\x{72AF}\x{72C0}\x{72FC}\x{732A}\x{7375}\x{737A}\x{7387}\x{738B}\x{73A5}\x{73B2}\x{73DE}\x{7406}\x{7409}\x{7422}\x{7447}\x{745C}\x{7469}\x{7471}\x{7485}\x{7489}\x{7498}\x{74CA}\x{7506}\x{7524}\x{753B}\x{753E}\x{7559}\x{7565}\x{7570}\x{75E2}\x{7610}\x{761D}\x{761F}\x{7642}\x{7669}\x{76CA}\x{76DB}\x{76E7}\x{76F4}\x{7701}\x{771E}-\x{771F}\x{7740}\x{774A}\x{778B}\x{77A7}\x{784E}\x{786B}\x{788C}\x{7891}\x{78CA}\x{78CC}\x{78FB}\x{792A}\x{793C}\x{793E}\x{7948}-\x{7949}\x{7950}\x{7956}\x{795D}-\x{795E}\x{7965}\x{797F}\x{798D}-\x{798F}\x{79AE}\x{79CA}\x{79EB}\x{7A1C}\x{7A40}\x{7A4A}\x{7A4F}\x{7A81}\x{7AB1}\x{7ACB}\x{7AEE}\x{7B20}\x{7BC0}\x{7BC6}\x{7BC9}\x{7C3E}\x{7C60}\x{7C7B}\x{7C92}\x{7CBE}\x{7CD2}\x{7CD6}\x{7CE3}\x{7CE7}-\x{7CE8}\x{7D00}\x{7D10}\x{7D22}\x{7D2F}\x{7D5B}\x{7D63}\x{7DA0}\x{7DBE}\x{7DC7}\x{7DF4}\x{7E02}\x{7E09}\x{7E37}\x{7E41}\x{7E45}\x{7F3E}\x{7F72}\x{7F79}-\x{7F7A}\x{7F85}\x{7F95}\x{7F9A}\x{7FBD}\x{7FFA}\x{8001}\x{8005}\x{8046}\x{8060}\x{806F}-\x{8070}\x{807E}\x{808B}\x{80AD}\x{80B2}\x{8103}\x{813E}\x{81D8}\x{81E8}\x{81ED}\x{8201}\x{8204}\x{8218}\x{826F}\x{8279}\x{828B}\x{8291}\x{829D}\x{82B1}\x{82B3}\x{82BD}\x{82E5}-\x{82E6}\x{831D}\x{8323}\x{8336}\x{8352}-\x{8353}\x{8363}\x{83AD}\x{83BD}\x{83C9}-\x{83CA}\x{83CC}\x{83DC}\x{83E7}\x{83EF}\x{83F1}\x{843D}\x{8449}\x{8457}\x{84EE}\x{84F1}\x{84F3}\x{84FC}\x{8516}\x{8564}\x{85CD}\x{85FA}\x{8606}\x{8612}\x{862D}\x{863F}\x{8650}\x{865C}\x{8667}\x{8669}\x{8688}\x{86A9}\x{86E2}\x{870E}\x{8728}\x{876B}\x{8779}\x{8786}\x{87BA}\x{87E1}\x{8801}\x{881F}\x{884C}\x{8860}\x{8863}\x{88C2}\x{88CF}\x{88D7}\x{88DE}\x{88E1}\x{88F8}\x{88FA}\x{8910}\x{8941}\x{8964}\x{8986}\x{898B}\x{8996}\x{8AA0}\x{8AAA}\x{8ABF}\x{8ACB}\x{8AD2}\x{8AD6}\x{8AED}\x{8AF8}\x{8AFE}\x{8B01}\x{8B39}\x{8B58}\x{8B80}\x{8B8A}\x{8C48}\x{8C55}\x{8CAB}\x{8CC1}-\x{8CC2}\x{8CC8}\x{8CD3}\x{8D08}\x{8D1B}\x{8D77}\x{8DBC}\x{8DCB}\x{8DEF}-\x{8DF0}\x{8ECA}\x{8ED4}\x{8F26}\x{8F2A}\x{8F38}\x{8F3B}\x{8F62}\x{8F9E}\x{8FB0}\x{8FB6}\x{9023}\x{9038}\x{9072}\x{907C}\x{908F}\x{9094}\x{90CE}\x{90DE}\x{90F1}\x{90FD}\x{9111}\x{911B}\x{916A}\x{9199}\x{91B4}\x{91CC}\x{91CF}\x{91D1}\x{9234}\x{9238}\x{9276}\x{927C}\x{92D7}-\x{92D8}\x{9304}\x{934A}\x{93F9}\x{9415}\x{958B}\x{95AD}\x{95B7}\x{962E}\x{964B}\x{964D}\x{9675}\x{9678}\x{967C}\x{9686}\x{96A3}\x{96B7}-\x{96B8}\x{96C3}\x{96E2}-\x{96E3}\x{96F6}-\x{96F7}\x{9723}\x{9732}\x{9748}\x{9756}\x{97DB}\x{97E0}\x{97FF}\x{980B}\x{9818}\x{9829}\x{983B}\x{985E}\x{98E2}\x{98EF}\x{98FC}\x{9928}-\x{9929}\x{99A7}\x{99C2}\x{99F1}\x{99FE}\x{9A6A}\x{9B12}\x{9B6F}\x{9C40}\x{9C57}\x{9CFD}\x{9D67}\x{9DB4}\x{9DFA}\x{9E1E}\x{9E7F}\x{9E97}\x{9E9F}\x{9EBB}\x{9ECE}\x{9EF9}\x{9EFE}\x{9F05}\x{9F0F}\x{9F16}\x{9F3B}\x{9F43}\x{9F8D}-\x{9F8E}\x{9F9C}\x{20122}\x{2051C}\x{20525}\x{2054B}\x{2063A}\x{20804}\x{208DE}\x{20A2C}\x{20B63}\x{214E4}\x{216A8}\x{216EA}\x{219C8}\x{21B18}\x{21D0B}\x{21DE4}\x{21DE6}\x{22183}\x{2219F}\x{22331}\x{226D4}\x{22844}\x{2284A}\x{22B0C}\x{22BF1}\x{2300A}\x{232B8}\x{2335F}\x{23393}\x{2339C}\x{233C3}\x{233D5}\x{2346D}\x{236A3}\x{238A7}\x{23A8D}\x{23AFA}\x{23CBC}\x{23D1E}\x{23ED1}\x{23F5E}\x{23F8E}\x{24263}\x{242EE}\x{243AB}\x{24608}\x{24735}\x{24814}\x{24C36}\x{24C92}\x{24FA1}\x{24FB8}\x{25044}\x{250F2}-\x{250F3}\x{25119}\x{25133}\x{25249}\x{2541D}\x{25626}\x{2569A}\x{256C5}\x{2597C}\x{25AA7}\x{25BAB}\x{25C80}\x{25CD0}\x{25F86}\x{261DA}\x{26228}\x{26247}\x{262D9}\x{2633E}\x{264DA}\x{26523}\x{265A8}\x{267A7}\x{267B5}\x{26B3C}\x{26C36}\x{26CD5}\x{26D6B}\x{26F2C}\x{26FB1}\x{270D2}\x{273CA}\x{27667}\x{278AE}\x{27966}\x{27CA8}\x{27ED3}\x{27F2F}\x{285D2}\x{285ED}\x{2872E}\x{28BFA}\x{28D77}\x{29145}\x{291DF}\x{2921A}\x{2940A}\x{29496}\x{295B6}\x{29B30}\x{2A0CE}\x{2A105}\x{2A20E}\x{2A291}\x{2A392}\x{2A600}',
		'\x{180D}' => '\x{1828}\x{182C}-\x{182D}\x{1873}-\x{1874}\x{1887}',
		'\x{180C}' => '\x{1820}\x{1825}-\x{1826}\x{1828}\x{182C}-\x{182D}\x{1830}\x{1836}\x{1847}\x{185E}\x{1868}\x{1873}-\x{1874}\x{1887}',
		'\x{180B}' => '\x{1820}-\x{1826}\x{1828}\x{182A}\x{182C}-\x{182D}\x{1830}\x{1832}-\x{1833}\x{1835}-\x{1836}\x{1838}\x{1844}-\x{1849}\x{184D}-\x{184E}\x{185D}-\x{185E}\x{1860}\x{1863}\x{1868}-\x{1869}\x{186F}\x{1873}-\x{1874}\x{1876}\x{1880}-\x{1881}\x{1887}-\x{1888}\x{188A}',
	);
}

/**
 * Helper function for utf8_sanitize_invisibles.
 *
 * Character class lists compiled from:
 * https://unicode.org/Public/UNIDATA/extracted/DerivedJoiningType.txt
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Character classes for joining characters in certain scripts.
 */
function utf8_regex_joining_type()
{
	return array(
		'Arabic' => array(
			'Join_Causing' => '\x{0640}\x{0883}-\x{0885}',
			'Dual_Joining' => '\x{0620}\x{0626}\x{0628}\x{062A}-\x{062E}\x{0633}-\x{063F}\x{0641}-\x{0647}\x{0649}-\x{064A}\x{066E}-\x{066F}\x{0678}-\x{0687}\x{069A}-\x{06BF}\x{06C1}-\x{06C2}\x{06CC}\x{06CE}\x{06D0}-\x{06D1}\x{06FA}-\x{06FC}\x{06FF}\x{075C}-\x{076A}\x{076D}-\x{0770}\x{0772}\x{0775}-\x{0777}\x{077A}-\x{077F}\x{0886}\x{0889}-\x{088D}\x{08A0}-\x{08A9}\x{08AF}-\x{08B0}\x{08B3}-\x{08B8}\x{08BA}-\x{08C8}',
			'Right_Joining' => '\x{0622}-\x{0625}\x{0627}\x{0629}\x{062F}-\x{0632}\x{0648}\x{0671}-\x{0673}\x{0675}-\x{0677}\x{0688}-\x{0699}\x{06C0}\x{06C3}-\x{06CB}\x{06CD}\x{06CF}\x{06D2}-\x{06D3}\x{06D5}\x{06EE}-\x{06EF}\x{0759}-\x{075B}\x{076B}-\x{076C}\x{0771}\x{0773}-\x{0774}\x{0778}-\x{0779}\x{0870}-\x{0882}\x{088E}\x{08AA}-\x{08AC}\x{08AE}\x{08B1}-\x{08B2}\x{08B9}',
			'Transparent' => '\x{0610}-\x{061A}\x{061C}\x{061C}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E4}\x{06E7}-\x{06E8}\x{06EA}-\x{06ED}\x{0898}-\x{089F}\x{08CA}-\x{08E1}\x{08E3}-\x{0902}\x{102E0}',
		),
		'Syriac' => array(
			'Join_Causing' => '\x{0640}',
			'Dual_Joining' => '\x{0712}-\x{0714}\x{071A}-\x{071D}\x{071F}-\x{0727}\x{0729}\x{072B}\x{072D}-\x{072E}\x{074E}-\x{0758}\x{0860}\x{0862}-\x{0865}\x{0868}',
			'Right_Joining' => '\x{0710}\x{0715}-\x{0719}\x{071E}\x{0728}\x{072A}\x{072C}\x{072F}\x{074D}\x{0867}\x{0869}-\x{086A}',
			'Transparent' => '\x{061C}\x{0670}\x{070F}\x{0711}\x{0730}-\x{074A}',
		),
		'Adlam' => array(
			'Join_Causing' => '\x{0640}',
			'Dual_Joining' => '\x{1E900}-\x{1E943}',
			'Transparent' => '\x{1E944}-\x{1E94A}\x{1E94B}',
		),
		'Tirhuta' => array(
			'Dual_Joining' => '\x{A840}-\x{A871}',
			'Transparent' => '\x{0951}-\x{0957}\x{114B3}-\x{114B8}\x{114BA}\x{114BF}-\x{114C0}\x{114C2}-\x{114C3}',
		),
		'Nko' => array(
			'Join_Causing' => '\x{07FA}',
			'Dual_Joining' => '\x{07CA}-\x{07EA}',
			'Transparent' => '\x{07EB}-\x{07F3}\x{07FD}',
		),
		'Hanifi_Rohingya' => array(
			'Join_Causing' => '\x{0640}',
			'Dual_Joining' => '\x{10D01}-\x{10D21}\x{10D23}',
			'Right_Joining' => '\x{10D22}',
			'Left_Joining' => '\x{10D00}',
			'Transparent' => '\x{10D24}-\x{10D27}',
		),
		'Manichaean' => array(
			'Join_Causing' => '\x{0640}',
			'Dual_Joining' => '\x{10AC0}-\x{10AC4}\x{10AD3}-\x{10AD6}\x{10AD8}-\x{10ADC}\x{10ADE}-\x{10AE0}\x{10AEB}-\x{10AEE}',
			'Right_Joining' => '\x{10AC5}\x{10AC7}\x{10AC9}-\x{10ACA}\x{10ACE}-\x{10AD2}\x{10ADD}\x{10AE1}\x{10AE4}\x{10AEF}',
			'Left_Joining' => '\x{10ACD}\x{10AD7}',
			'Transparent' => '\x{10AE5}-\x{10AE6}',
		),
		'Sogdian' => array(
			'Join_Causing' => '\x{0640}',
			'Dual_Joining' => '\x{10F30}-\x{10F32}\x{10F34}-\x{10F44}\x{10F51}-\x{10F53}',
			'Right_Joining' => '\x{10F33}\x{10F54}',
			'Transparent' => '\x{10F46}-\x{10F50}',
		),
		'Mandaic' => array(
			'Join_Causing' => '\x{0640}',
			'Dual_Joining' => '\x{0841}-\x{0845}\x{0848}\x{084A}-\x{0853}\x{0855}',
			'Right_Joining' => '\x{0840}\x{0846}-\x{0847}\x{0849}\x{0854}\x{0856}-\x{0858}',
			'Transparent' => '\x{0859}-\x{085B}',
		),
		'Psalter_Pahlavi' => array(
			'Join_Causing' => '\x{0640}',
			'Dual_Joining' => '\x{10B80}\x{10B82}\x{10B86}-\x{10B88}\x{10B8A}-\x{10B8B}\x{10B8D}\x{10B90}\x{10BAD}-\x{10BAE}',
			'Right_Joining' => '\x{10B81}\x{10B83}-\x{10B85}\x{10B89}\x{10B8C}\x{10B8E}-\x{10B8F}\x{10B91}\x{10BA9}-\x{10BAC}',
		),
		'Old_Uyghur' => array(
			'Join_Causing' => '\x{0640}',
			'Dual_Joining' => '\x{10F70}-\x{10F73}\x{10F76}-\x{10F81}',
			'Right_Joining' => '\x{10F74}-\x{10F75}',
			'Transparent' => '\x{10F82}-\x{10F85}',
		),
		'Mongolian' => array(
			'Join_Causing' => '\x{180A}',
			'Dual_Joining' => '\x{1807}\x{1820}-\x{1842}\x{1843}\x{1844}-\x{1878}\x{1887}-\x{18A8}\x{18AA}',
			'Transparent' => '\x{180B}-\x{180D}\x{180F}\x{1885}-\x{1886}\x{18A9}',
		),
		'Phags_Pa' => array(
			'Dual_Joining' => '\x{A840}-\x{A871}',
			'Left_Joining' => '\x{A872}',
		),
		'Chorasmian' => array(
			'Dual_Joining' => '\x{10FB0}\x{10FB2}-\x{10FB3}\x{10FB8}\x{10FBB}-\x{10FBC}\x{10FBE}-\x{10FBF}\x{10FC1}\x{10FC4}\x{10FCA}',
			'Right_Joining' => '\x{10FB4}-\x{10FB6}\x{10FB9}-\x{10FBA}\x{10FBD}\x{10FC2}-\x{10FC3}\x{10FC9}',
			'Left_Joining' => '\x{10FCB}',
		),
	);
}

/**
 * Helper function for utf8_sanitize_invisibles.
 *
 * Character class lists compiled from:
 * https://unicode.org/Public/UNIDATA/extracted/DerivedCombiningClass.txt
 * https://unicode.org/Public/UNIDATA/IndicSyllabicCategory.txt
 *
 * Developers: Do not update the data in this function manually. Instead,
 * run "php -f other/update_unicode_data.php" on the command line.
 *
 * @return array Character classes for Indic scripts that use viramas.
 */
function utf8_regex_indic()
{
	return array(
		'Devanagari' => array(
			'All' => '\x{0900}-\x{0952}\x{0955}-\x{0966}\x{0966}-\x{096A}\x{096A}-\x{096E}\x{096E}-\x{097F}\x{1CD0}-\x{1CD4}\x{1CD6}-\x{1CDC}\x{1CDE}-\x{1CF4}\x{1CF6}\x{1CF8}\x{20F0}\x{A830}\x{A833}\x{A836}\x{A838}-\x{A839}\x{A8E0}-\x{A8F1}\x{A8F1}-\x{A8F3}\x{A8F3}-\x{A8FF}',
			'Letter' => '\x{0904}-\x{0939}\x{093D}\x{0950}\x{0958}-\x{0961}\x{0971}-\x{097F}\x{1CE9}-\x{1CEC}\x{1CEE}-\x{1CF3}\x{1CF6}\x{A8F2}-\x{A8F3}\x{A8F3}-\x{A8F7}\x{A8FB}\x{A8FD}-\x{A8FE}',
			'Nonspacing_Combining_Mark' => '\x{093C}\x{094D}\x{0951}-\x{0952}\x{1CD0}-\x{1CD2}\x{1CD4}\x{1CD6}-\x{1CDC}\x{1CDE}-\x{1CE0}\x{1CE2}-\x{1CE8}\x{1CED}\x{1CF4}\x{1CF8}\x{20F0}\x{A8E0}-\x{A8F1}\x{A8F1}',
			'Nonspacing_Mark' => '\x{0900}-\x{0902}\x{093A}\x{093C}\x{0941}-\x{0948}\x{094D}\x{0951}-\x{0952}\x{0955}-\x{0957}\x{0962}-\x{0963}\x{1CD0}-\x{1CD2}\x{1CD4}\x{1CD6}-\x{1CDC}\x{1CDE}-\x{1CE0}\x{1CE2}-\x{1CE8}\x{1CED}\x{1CF4}\x{1CF8}\x{20F0}\x{A8E0}-\x{A8F1}\x{A8F1}\x{A8FF}',
			'Virama' => '\x{094D}',
			'Vowel_Dependent' => '\x{093A}\x{093B}\x{093E}-\x{0940}\x{0941}-\x{0948}\x{0949}-\x{094C}\x{094E}-\x{094F}\x{0955}-\x{0957}\x{0962}-\x{0963}\x{A8FF}',
		),
		'Bengali' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0980}-\x{0983}\x{0985}-\x{098C}\x{098F}-\x{0990}\x{0993}-\x{09A8}\x{09AA}-\x{09B0}\x{09B2}\x{09B6}-\x{09B9}\x{09BC}-\x{09C4}\x{09C7}-\x{09C8}\x{09CB}-\x{09CE}\x{09D7}\x{09DC}-\x{09DD}\x{09DF}-\x{09E3}\x{09E6}\x{09E6}-\x{09E9}\x{09E9}-\x{09EC}\x{09EC}-\x{09EF}\x{09EF}-\x{09FE}\x{1CD0}\x{1CD2}\x{1CD5}\x{1CD8}\x{1CE1}\x{1CEA}\x{1CED}\x{1CF2}\x{1CF5}\x{1CF7}\x{A8F1}',
			'Letter' => '\x{0980}\x{0985}-\x{098C}\x{098F}-\x{0990}\x{0993}-\x{09A8}\x{09AA}-\x{09B0}\x{09B2}\x{09B6}-\x{09B9}\x{09BD}\x{09CE}\x{09DC}-\x{09DD}\x{09DF}-\x{09E1}\x{09F0}-\x{09F1}\x{09FC}\x{1CEA}\x{1CF2}\x{1CF5}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{09BC}\x{09CD}\x{09FE}\x{1CD0}\x{1CD2}\x{1CD5}\x{1CD8}\x{1CED}\x{A8F1}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{0981}\x{09BC}\x{09C1}-\x{09C4}\x{09CD}\x{09E2}-\x{09E3}\x{09FE}\x{1CD0}\x{1CD2}\x{1CD5}\x{1CD8}\x{1CED}\x{A8F1}',
			'Virama' => '\x{09CD}',
			'Vowel_Dependent' => '\x{09BE}-\x{09C0}\x{09C1}-\x{09C4}\x{09C7}-\x{09C8}\x{09CB}-\x{09CC}\x{09D7}\x{09E2}-\x{09E3}',
		),
		'Grantha' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0BE6}\x{0BE8}\x{0BEA}\x{0BEC}\x{0BEE}\x{0BF0}\x{0BF2}-\x{0BF3}\x{1CD0}\x{1CD2}-\x{1CD3}\x{1CF2}-\x{1CF4}\x{1CF9}\x{20F0}\x{11300}-\x{11301}\x{11301}-\x{11303}\x{11303}\x{11305}-\x{1130C}\x{1130F}-\x{11310}\x{11313}-\x{11328}\x{1132A}-\x{11330}\x{11332}-\x{11333}\x{11335}-\x{11339}\x{1133B}-\x{11344}\x{11347}-\x{11348}\x{1134B}-\x{1134D}\x{11350}\x{11357}\x{1135D}-\x{11363}\x{11366}-\x{1136C}\x{11370}-\x{11374}\x{11FD0}\x{11FD3}',
			'Letter' => '\x{1CF2}-\x{1CF3}\x{11305}-\x{1130C}\x{1130F}-\x{11310}\x{11313}-\x{11328}\x{1132A}-\x{11330}\x{11332}-\x{11333}\x{11335}-\x{11339}\x{1133D}\x{11350}\x{1135D}-\x{11361}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{1CD0}\x{1CD2}\x{1CF4}\x{1CF9}\x{20F0}\x{1133B}-\x{1133C}\x{11366}-\x{1136C}\x{11370}-\x{11374}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{1CD0}\x{1CD2}\x{1CF4}\x{1CF9}\x{20F0}\x{11300}-\x{11301}\x{11301}\x{1133B}-\x{1133C}\x{11340}\x{11366}-\x{1136C}\x{11370}-\x{11374}',
			'Virama' => '\x{1134D}',
			'Vowel_Dependent' => '\x{1133E}-\x{1133F}\x{11340}\x{11341}-\x{11344}\x{11347}-\x{11348}\x{1134B}-\x{1134C}\x{11357}\x{11362}-\x{11363}',
		),
		'Tirhuta' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{1CF2}\x{A838}-\x{A839}\x{A83D}\x{A83F}-\x{A840}\x{11480}-\x{114C7}\x{114D0}-\x{114D9}',
			'Letter' => '\x{1CF2}\x{A840}\x{11480}-\x{114AF}\x{114C4}-\x{114C5}\x{114C7}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{114C2}-\x{114C3}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{114B3}-\x{114B8}\x{114BA}\x{114BF}-\x{114C0}\x{114C2}-\x{114C3}',
			'Virama' => '\x{114C2}',
			'Vowel_Dependent' => '\x{114B0}-\x{114B2}\x{114B3}-\x{114B8}\x{114B9}\x{114BA}\x{114BB}-\x{114BE}',
		),
		'Nandinagari' => array(
			'All' => '\x{0964}-\x{0965}\x{0CE7}\x{0CE9}\x{0CEB}\x{0CED}\x{0CEF}\x{1CE9}\x{1CF2}\x{1CFA}\x{A83A}\x{A83C}\x{119A0}-\x{119A7}\x{119AA}-\x{119D7}\x{119DA}-\x{119E4}',
			'Letter' => '\x{1CE9}\x{1CF2}\x{1CFA}\x{119A0}-\x{119A7}\x{119AA}-\x{119D0}\x{119E1}\x{119E3}',
			'Nonspacing_Combining_Mark' => '\x{119E0}',
			'Nonspacing_Mark' => '\x{119D4}-\x{119D7}\x{119DA}-\x{119DB}\x{119E0}',
			'Virama' => '\x{119E0}',
			'Vowel_Dependent' => '\x{119D1}-\x{119D3}\x{119D4}-\x{119D7}\x{119DA}-\x{119DB}\x{119DC}-\x{119DD}\x{119E4}',
		),
		'Takri' => array(
			'All' => '\x{0964}-\x{0965}\x{A838}-\x{A839}\x{A83C}\x{A83E}-\x{A83F}\x{11680}-\x{116B9}\x{116C0}-\x{116C9}',
			'Letter' => '\x{11680}-\x{116AA}\x{116B8}',
			'Nonspacing_Combining_Mark' => '\x{116B7}',
			'Nonspacing_Mark' => '\x{116AB}\x{116AD}\x{116B0}-\x{116B5}\x{116B7}',
			'Virama' => '\x{116B6}',
			'Vowel_Dependent' => '\x{116AD}\x{116AE}-\x{116AF}\x{116B0}-\x{116B5}',
		),
		'Khojki' => array(
			'All' => '\x{0AE7}\x{0AE9}\x{0AEB}\x{0AED}\x{0AEF}\x{A834}\x{A837}-\x{A83A}\x{11200}-\x{11211}\x{11213}-\x{1123E}',
			'Letter' => '\x{11200}-\x{11211}\x{11213}-\x{1122B}',
			'Nonspacing_Combining_Mark' => '\x{11236}',
			'Nonspacing_Mark' => '\x{1122F}-\x{11231}\x{11234}\x{11236}-\x{11237}\x{1123E}',
			'Virama' => '\x{11235}',
			'Vowel_Dependent' => '\x{1122C}-\x{1122E}\x{1122F}-\x{11231}\x{11232}-\x{11233}',
		),
		'Dogra' => array(
			'All' => '\x{0964}-\x{0965}\x{0967}\x{096B}\x{096F}\x{A831}\x{A834}\x{A837}-\x{A839}\x{11800}-\x{1183B}',
			'Letter' => '\x{11800}-\x{1182B}',
			'Nonspacing_Combining_Mark' => '\x{11839}-\x{1183A}',
			'Nonspacing_Mark' => '\x{1182F}-\x{11837}\x{11839}-\x{1183A}',
			'Virama' => '\x{11839}',
			'Vowel_Dependent' => '\x{1182C}-\x{1182E}\x{1182F}-\x{11836}',
		),
		'Tamil' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0B82}-\x{0B83}\x{0B85}-\x{0B8A}\x{0B8E}-\x{0B90}\x{0B92}-\x{0B95}\x{0B99}-\x{0B9A}\x{0B9C}\x{0B9E}-\x{0B9F}\x{0BA3}-\x{0BA4}\x{0BA8}-\x{0BAA}\x{0BAE}-\x{0BB9}\x{0BBE}-\x{0BC2}\x{0BC6}-\x{0BC8}\x{0BCA}-\x{0BCD}\x{0BD0}\x{0BD7}\x{0BE6}-\x{0BE7}\x{0BE7}-\x{0BE9}\x{0BE9}-\x{0BEB}\x{0BEB}-\x{0BED}\x{0BED}-\x{0BEF}\x{0BEF}-\x{0BF1}\x{0BF1}-\x{0BF3}\x{0BF3}\x{0BF3}-\x{0BFA}\x{1CDA}\x{A8F3}\x{11301}\x{11303}\x{1133C}\x{11FC0}-\x{11FD1}\x{11FD1}-\x{11FD3}\x{11FD3}-\x{11FF1}\x{11FFF}',
			'Letter' => '\x{0B83}\x{0B85}-\x{0B8A}\x{0B8E}-\x{0B90}\x{0B92}-\x{0B95}\x{0B99}-\x{0B9A}\x{0B9C}\x{0B9E}-\x{0B9F}\x{0BA3}-\x{0BA4}\x{0BA8}-\x{0BAA}\x{0BAE}-\x{0BB9}\x{0BD0}\x{A8F3}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{0BCD}\x{1CDA}\x{1133C}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{0B82}\x{0BC0}\x{0BCD}\x{1CDA}\x{11301}\x{1133C}',
			'Virama' => '\x{0BCD}',
			'Vowel_Dependent' => '\x{0BBE}-\x{0BBF}\x{0BC0}\x{0BC1}-\x{0BC2}\x{0BC6}-\x{0BC8}\x{0BCA}-\x{0BCC}\x{0BD7}',
		),
		'Malayalam' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0D00}-\x{0D0C}\x{0D0E}-\x{0D10}\x{0D12}-\x{0D44}\x{0D46}-\x{0D48}\x{0D4A}-\x{0D4F}\x{0D54}-\x{0D63}\x{0D66}-\x{0D7F}\x{1CDA}\x{A838}',
			'Letter' => '\x{0D04}-\x{0D0C}\x{0D0E}-\x{0D10}\x{0D12}-\x{0D3A}\x{0D3D}\x{0D4E}\x{0D54}-\x{0D56}\x{0D5F}-\x{0D61}\x{0D7A}-\x{0D7F}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{0D3B}-\x{0D3C}\x{0D4D}\x{1CDA}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{0D00}-\x{0D01}\x{0D3B}-\x{0D3C}\x{0D41}-\x{0D44}\x{0D4D}\x{0D62}-\x{0D63}\x{1CDA}',
			'Virama' => '\x{0D4D}',
			'Vowel_Dependent' => '\x{0D3E}-\x{0D40}\x{0D41}-\x{0D44}\x{0D46}-\x{0D48}\x{0D4A}-\x{0D4C}\x{0D57}\x{0D62}-\x{0D63}',
		),
		'Sinhala' => array(
			'All' => '\x{0964}-\x{0965}\x{0D81}-\x{0D83}\x{0D85}-\x{0D96}\x{0D9A}-\x{0DB1}\x{0DB3}-\x{0DBB}\x{0DBD}\x{0DC0}-\x{0DC6}\x{0DCA}\x{0DCF}-\x{0DD4}\x{0DD6}\x{0DD8}-\x{0DDF}\x{0DE6}-\x{0DEF}\x{0DF2}-\x{0DF4}\x{111E1}-\x{111F4}',
			'Letter' => '\x{0D85}-\x{0D96}\x{0D9A}-\x{0DB1}\x{0DB3}-\x{0DBB}\x{0DBD}\x{0DC0}-\x{0DC6}',
			'Nonspacing_Combining_Mark' => '\x{0DCA}',
			'Nonspacing_Mark' => '\x{0D81}\x{0DCA}\x{0DD2}-\x{0DD4}\x{0DD6}',
			'Virama' => '\x{0DCA}',
			'Vowel_Dependent' => '\x{0DCF}-\x{0DD1}\x{0DD2}-\x{0DD4}\x{0DD6}\x{0DD8}-\x{0DDF}\x{0DF2}-\x{0DF3}',
		),
		'Telugu' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0C00}-\x{0C0C}\x{0C0E}-\x{0C10}\x{0C12}-\x{0C28}\x{0C2A}-\x{0C39}\x{0C3C}-\x{0C44}\x{0C46}-\x{0C48}\x{0C4A}-\x{0C4D}\x{0C55}-\x{0C56}\x{0C58}-\x{0C5A}\x{0C5D}\x{0C60}-\x{0C63}\x{0C66}-\x{0C6F}\x{0C77}-\x{0C7F}\x{1CDA}\x{1CF2}',
			'Letter' => '\x{0C05}-\x{0C0C}\x{0C0E}-\x{0C10}\x{0C12}-\x{0C28}\x{0C2A}-\x{0C39}\x{0C3D}\x{0C58}-\x{0C5A}\x{0C5D}\x{0C60}-\x{0C61}\x{1CF2}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{0C3C}\x{0C4D}\x{0C55}-\x{0C56}\x{1CDA}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{0C00}\x{0C04}\x{0C3C}\x{0C3E}-\x{0C40}\x{0C46}-\x{0C48}\x{0C4A}-\x{0C4D}\x{0C55}-\x{0C56}\x{0C62}-\x{0C63}\x{1CDA}',
			'Virama' => '\x{0C4D}',
			'Vowel_Dependent' => '\x{0C3E}-\x{0C40}\x{0C41}-\x{0C44}\x{0C46}-\x{0C48}\x{0C4A}-\x{0C4C}\x{0C55}-\x{0C56}\x{0C62}-\x{0C63}',
		),
		'Kannada' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0C80}-\x{0C8C}\x{0C8E}-\x{0C90}\x{0C92}-\x{0CA8}\x{0CAA}-\x{0CB3}\x{0CB5}-\x{0CB9}\x{0CBC}-\x{0CC4}\x{0CC6}-\x{0CC8}\x{0CCA}-\x{0CCD}\x{0CD5}-\x{0CD6}\x{0CDD}-\x{0CDE}\x{0CE0}-\x{0CE3}\x{0CE6}\x{0CE6}-\x{0CE8}\x{0CE8}-\x{0CEA}\x{0CEA}-\x{0CEC}\x{0CEC}-\x{0CEE}\x{0CEE}-\x{0CEF}\x{0CF1}-\x{0CF2}\x{1CD0}\x{1CD2}\x{1CDA}\x{1CF2}\x{1CF4}\x{A835}\x{A838}',
			'Letter' => '\x{0C80}\x{0C85}-\x{0C8C}\x{0C8E}-\x{0C90}\x{0C92}-\x{0CA8}\x{0CAA}-\x{0CB3}\x{0CB5}-\x{0CB9}\x{0CBD}\x{0CDD}-\x{0CDE}\x{0CE0}-\x{0CE1}\x{0CF1}-\x{0CF2}\x{1CF2}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{0CBC}\x{0CCD}\x{1CD0}\x{1CD2}\x{1CDA}\x{1CF4}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{0C81}\x{0CBC}\x{0CBF}\x{0CC6}\x{0CCC}-\x{0CCD}\x{0CE2}-\x{0CE3}\x{1CD0}\x{1CD2}\x{1CDA}\x{1CF4}',
			'Virama' => '\x{0CCD}',
			'Vowel_Dependent' => '\x{0CBE}\x{0CBF}\x{0CC0}-\x{0CC4}\x{0CC6}\x{0CC7}-\x{0CC8}\x{0CCA}-\x{0CCB}\x{0CCC}\x{0CD5}-\x{0CD6}\x{0CE2}-\x{0CE3}',
		),
		'Gujarati' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0A81}-\x{0A83}\x{0A85}-\x{0A8D}\x{0A8F}-\x{0A91}\x{0A93}-\x{0AA8}\x{0AAA}-\x{0AB0}\x{0AB2}-\x{0AB3}\x{0AB5}-\x{0AB9}\x{0ABC}-\x{0AC5}\x{0AC7}-\x{0AC9}\x{0ACB}-\x{0ACD}\x{0AD0}\x{0AE0}-\x{0AE3}\x{0AE6}\x{0AE6}-\x{0AE8}\x{0AE8}-\x{0AEA}\x{0AEA}-\x{0AEC}\x{0AEC}-\x{0AEE}\x{0AEE}-\x{0AF1}\x{0AF9}-\x{0AFF}\x{A832}\x{A835}\x{A838}\x{A838}-\x{A839}',
			'Letter' => '\x{0A85}-\x{0A8D}\x{0A8F}-\x{0A91}\x{0A93}-\x{0AA8}\x{0AAA}-\x{0AB0}\x{0AB2}-\x{0AB3}\x{0AB5}-\x{0AB9}\x{0ABD}\x{0AD0}\x{0AE0}-\x{0AE1}\x{0AF9}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{0ABC}\x{0ACD}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{0A81}-\x{0A82}\x{0ABC}\x{0AC1}-\x{0AC5}\x{0AC7}-\x{0AC8}\x{0ACD}\x{0AE2}-\x{0AE3}\x{0AFA}-\x{0AFF}',
			'Virama' => '\x{0ACD}',
			'Vowel_Dependent' => '\x{0ABE}-\x{0AC0}\x{0AC1}-\x{0AC5}\x{0AC7}-\x{0AC8}\x{0AC9}\x{0ACB}-\x{0ACC}\x{0AE2}-\x{0AE3}',
		),
		'Sharada' => array(
			'All' => '\x{0951}\x{1CD7}\x{1CD9}\x{1CDD}\x{1CE0}\x{11180}-\x{111DF}',
			'Letter' => '\x{11183}-\x{111B2}\x{111C1}-\x{111C4}\x{111DA}\x{111DC}',
			'Nonspacing_Combining_Mark' => '\x{0951}\x{1CD7}\x{1CD9}\x{1CDD}\x{1CE0}\x{111CA}',
			'Nonspacing_Mark' => '\x{0951}\x{1CD7}\x{1CD9}\x{1CDD}\x{1CE0}\x{11180}-\x{11181}\x{111B6}-\x{111BE}\x{111C9}-\x{111CC}\x{111CF}',
			'Virama' => '\x{111C0}',
			'Vowel_Dependent' => '\x{111B3}-\x{111B5}\x{111B6}-\x{111BE}\x{111BF}\x{111CB}-\x{111CC}\x{111CE}',
		),
		'Oriya' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0B01}-\x{0B03}\x{0B05}-\x{0B0C}\x{0B0F}-\x{0B10}\x{0B13}-\x{0B28}\x{0B2A}-\x{0B30}\x{0B32}-\x{0B33}\x{0B35}-\x{0B39}\x{0B3C}-\x{0B44}\x{0B47}-\x{0B48}\x{0B4B}-\x{0B4D}\x{0B55}-\x{0B57}\x{0B5C}-\x{0B5D}\x{0B5F}-\x{0B63}\x{0B66}-\x{0B77}\x{1CDA}\x{1CF2}',
			'Letter' => '\x{0B05}-\x{0B0C}\x{0B0F}-\x{0B10}\x{0B13}-\x{0B28}\x{0B2A}-\x{0B30}\x{0B32}-\x{0B33}\x{0B35}-\x{0B39}\x{0B3D}\x{0B5C}-\x{0B5D}\x{0B5F}-\x{0B61}\x{0B71}\x{1CF2}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{0B3C}\x{0B4D}\x{1CDA}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{0B01}\x{0B3C}\x{0B3F}\x{0B41}-\x{0B44}\x{0B4D}\x{0B55}-\x{0B56}\x{0B62}-\x{0B63}\x{1CDA}',
			'Virama' => '\x{0B4D}',
			'Vowel_Dependent' => '\x{0B3E}\x{0B3F}\x{0B40}\x{0B41}-\x{0B44}\x{0B47}-\x{0B48}\x{0B4B}-\x{0B4C}\x{0B55}-\x{0B56}\x{0B57}\x{0B62}-\x{0B63}',
		),
		'Gurmukhi' => array(
			'All' => '\x{0951}-\x{0952}\x{0964}-\x{0965}\x{0A01}-\x{0A03}\x{0A05}-\x{0A0A}\x{0A0F}-\x{0A10}\x{0A13}-\x{0A28}\x{0A2A}-\x{0A30}\x{0A32}-\x{0A33}\x{0A35}-\x{0A36}\x{0A38}-\x{0A39}\x{0A3C}\x{0A3E}-\x{0A42}\x{0A47}-\x{0A48}\x{0A4B}-\x{0A4D}\x{0A51}\x{0A59}-\x{0A5C}\x{0A5E}\x{0A66}\x{0A66}-\x{0A68}\x{0A68}-\x{0A6A}\x{0A6A}-\x{0A6C}\x{0A6C}-\x{0A6E}\x{0A6E}-\x{0A76}\x{A833}\x{A836}\x{A838}-\x{A839}\x{A839}',
			'Letter' => '\x{0A05}-\x{0A0A}\x{0A0F}-\x{0A10}\x{0A13}-\x{0A28}\x{0A2A}-\x{0A30}\x{0A32}-\x{0A33}\x{0A35}-\x{0A36}\x{0A38}-\x{0A39}\x{0A59}-\x{0A5C}\x{0A5E}\x{0A72}-\x{0A74}',
			'Nonspacing_Combining_Mark' => '\x{0951}-\x{0952}\x{0A3C}\x{0A4D}',
			'Nonspacing_Mark' => '\x{0951}-\x{0952}\x{0A01}-\x{0A02}\x{0A3C}\x{0A41}-\x{0A42}\x{0A47}-\x{0A48}\x{0A4B}-\x{0A4D}\x{0A51}\x{0A70}-\x{0A71}\x{0A75}',
			'Virama' => '\x{0A4D}',
			'Vowel_Dependent' => '\x{0A3E}-\x{0A40}\x{0A41}-\x{0A42}\x{0A47}-\x{0A48}\x{0A4B}-\x{0A4C}',
		),
		'Kaithi' => array(
			'All' => '\x{0968}\x{096C}\x{0970}\x{A836}\x{A838}-\x{A839}\x{A839}\x{A83B}\x{11080}-\x{110C2}\x{110CD}',
			'Letter' => '\x{11083}-\x{110AF}',
			'Nonspacing_Combining_Mark' => '\x{110B9}-\x{110BA}',
			'Nonspacing_Mark' => '\x{11080}-\x{11081}\x{110B3}-\x{110B6}\x{110B9}-\x{110BA}\x{110C2}',
			'Virama' => '\x{110B9}',
			'Vowel_Dependent' => '\x{110B0}-\x{110B2}\x{110B3}-\x{110B6}\x{110B7}-\x{110B8}\x{110C2}',
		),
		'Syloti_Nagri' => array(
			'All' => '\x{0964}-\x{0965}\x{09E8}\x{09EB}\x{09EE}\x{09F1}\x{A800}-\x{A82C}',
			'Letter' => '\x{09F1}\x{A800}-\x{A801}\x{A803}-\x{A805}\x{A807}-\x{A80A}\x{A80C}-\x{A822}',
			'Nonspacing_Combining_Mark' => '\x{A806}\x{A82C}',
			'Nonspacing_Mark' => '\x{A802}\x{A806}\x{A80B}\x{A825}-\x{A826}\x{A82C}',
			'Virama' => '\x{A806}',
			'Vowel_Dependent' => '\x{A802}\x{A823}-\x{A824}\x{A825}-\x{A826}\x{A827}',
		),
		'Brahmi' => array(
			'All' => '\x{11000}-\x{1104D}\x{11052}-\x{11075}\x{1107F}',
			'Letter' => '\x{11003}-\x{11037}\x{11071}-\x{11072}\x{11075}',
			'Nonspacing_Combining_Mark' => '\x{11046}\x{11070}\x{1107F}',
			'Nonspacing_Mark' => '\x{11001}\x{11038}-\x{11046}\x{11070}\x{11073}-\x{11074}\x{1107F}',
			'Virama' => '\x{11046}',
			'Vowel_Dependent' => '\x{11038}-\x{11045}\x{11073}-\x{11074}',
		),
		'Javanese' => array(
			'All' => '\x{A980}-\x{A9CD}\x{A9CF}-\x{A9D9}\x{A9DE}-\x{A9DF}',
			'Letter' => '\x{A984}-\x{A9B2}\x{A9CF}',
			'Nonspacing_Combining_Mark' => '\x{A9B3}',
			'Nonspacing_Mark' => '\x{A980}-\x{A982}\x{A9B3}\x{A9B6}-\x{A9B9}\x{A9BC}-\x{A9BD}',
			'Virama' => '\x{A9C0}',
			'Vowel_Dependent' => '\x{A9B4}-\x{A9B5}\x{A9B6}-\x{A9B9}\x{A9BA}-\x{A9BB}\x{A9BC}',
		),
		'Modi' => array(
			'All' => '\x{A838}-\x{A839}\x{A839}\x{A83B}\x{A83D}\x{11600}-\x{11644}\x{11650}-\x{11659}',
			'Letter' => '\x{11600}-\x{1162F}\x{11644}',
			'Nonspacing_Combining_Mark' => '\x{1163F}',
			'Nonspacing_Mark' => '\x{11633}-\x{1163A}\x{1163D}\x{1163F}-\x{11640}',
			'Virama' => '\x{1163F}',
			'Vowel_Dependent' => '\x{11630}-\x{11632}\x{11633}-\x{1163A}\x{1163B}-\x{1163C}\x{11640}',
		),
		'Saurashtra' => array(
			'All' => '\x{A880}-\x{A8C5}\x{A8CE}-\x{A8D9}',
			'Letter' => '\x{A882}-\x{A8B3}',
			'Nonspacing_Combining_Mark' => '\x{A8C4}',
			'Nonspacing_Mark' => '\x{A8C4}-\x{A8C5}',
			'Virama' => '\x{A8C4}',
			'Vowel_Dependent' => '\x{A8B5}-\x{A8C3}',
		),
		'Balinese' => array(
			'All' => '\x{1B00}-\x{1B4C}\x{1B50}-\x{1B7E}',
			'Letter' => '\x{1B05}-\x{1B33}\x{1B45}-\x{1B4C}',
			'Nonspacing_Combining_Mark' => '\x{1B34}\x{1B6B}-\x{1B73}',
			'Nonspacing_Mark' => '\x{1B00}-\x{1B03}\x{1B34}\x{1B36}-\x{1B3A}\x{1B3C}\x{1B42}\x{1B6B}-\x{1B73}',
			'Virama' => '\x{1B44}',
			'Vowel_Dependent' => '\x{1B35}\x{1B36}-\x{1B3A}\x{1B3B}\x{1B3C}\x{1B3D}-\x{1B41}\x{1B42}\x{1B43}',
		),
		'Siddham' => array(
			'All' => '\x{11580}-\x{115B5}\x{115B8}-\x{115DD}',
			'Letter' => '\x{11580}-\x{115AE}\x{115D8}-\x{115DB}',
			'Nonspacing_Combining_Mark' => '\x{115BF}-\x{115C0}',
			'Nonspacing_Mark' => '\x{115B2}-\x{115B5}\x{115BC}-\x{115BD}\x{115BF}-\x{115C0}\x{115DC}-\x{115DD}',
			'Virama' => '\x{115BF}',
			'Vowel_Dependent' => '\x{115AF}-\x{115B1}\x{115B2}-\x{115B5}\x{115B8}-\x{115BB}\x{115DC}-\x{115DD}',
		),
		'Newa' => array(
			'All' => '\x{11400}-\x{1145B}\x{1145D}-\x{11461}',
			'Letter' => '\x{11400}-\x{11434}\x{11447}-\x{1144A}\x{1145F}-\x{11461}',
			'Nonspacing_Combining_Mark' => '\x{11442}\x{11446}\x{1145E}',
			'Nonspacing_Mark' => '\x{11438}-\x{1143F}\x{11442}-\x{11444}\x{11446}\x{1145E}',
			'Virama' => '\x{11442}',
			'Vowel_Dependent' => '\x{11435}-\x{11437}\x{11438}-\x{1143F}\x{11440}-\x{11441}',
		),
		'Bhaiksuki' => array(
			'All' => '\x{11C00}-\x{11C08}\x{11C0A}-\x{11C36}\x{11C38}-\x{11C45}\x{11C50}-\x{11C6C}',
			'Letter' => '\x{11C00}-\x{11C08}\x{11C0A}-\x{11C2E}\x{11C40}',
			'Nonspacing_Combining_Mark' => '\x{11C3F}',
			'Nonspacing_Mark' => '\x{11C30}-\x{11C36}\x{11C38}-\x{11C3D}\x{11C3F}',
			'Virama' => '\x{11C3F}',
			'Vowel_Dependent' => '\x{11C2F}\x{11C30}-\x{11C36}\x{11C38}-\x{11C3B}',
		),
	);
}

?>