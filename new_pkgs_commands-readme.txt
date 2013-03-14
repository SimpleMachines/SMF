
Changes:
--------
- added ability to use input and/or textarea HTML5 commands for uninstall/install template


Files affected:
---------------
/Sources/Packages.php
/Sources/Subs-Package.php
/Themes/default/Packages.template.php


Syntax
------

Examples:
---------
<input>{type="checkbox"}{name="delete_tables"}{value="1"}{text_after="Remove all database tables related to this modification?"}{checked="false"}{break="2"}{style="position:relative;top:2px;"}</input>
<input>{type="textarea"}{name="info"}{value="Comments"}{text_after="Enter your comments"}{style_after="position:relative;bottom:25px;color:blue;"}{style="color:red;"}{tabindex="5"}{rows="3"}{cols="50"}</input>


Explanation:
------------
  The commands are filtered in Subs-Packages.php in an attempt to thwart errors from the package-info.xml file.
Although it is up to the mod author to use the proper syntax, this was done to help ensure the xml code was entered correct;y/
Each command has to be separated by encased hard brackets and their values must be in quotations.
Additional filtering can be added to thwart unnecessary white spaces in certain areas but as I said the mod author should use appropriate syntax.
The command names and values are actual HTML5 syntax where some features were omitted as imo they do not apply for the packages template.
Text can be displayed left and/or right of the input/textarea where each can have its own value & style/class elements.
Style & class elements (css) can also be configured for the actual input/textarea.

The form passes the user entered values via the $context['new_inputs'] array where the key values are the name values entered by the author.
The $context['new_inputs'] array can be processed in any of their <code> files where they can manipulate the logic to do as they wish.

Allowed input types:
--------------------
button, checkbox, color, date, datetime, datetime-local, email, hidden, image, month,
number, password, radio, range, reset, search, submit, tel, text, textarea, time, url, week


Allowed input/textare attributes:
---------------------------------
alt           => for image                      (string)

autocomplete  => for text, search, url, tel,    (string - on or off)
                 password, datepickers, range,
                 email, color

autofocus     => for all types                  (true)

break         => for all types                  (integer - inserts additional line breaks after the input/textarea)

checked       => for radio, checkbox            (true)

class         => for all types                  (string - css class for input/textarea)

class_before  => for all types                  (string - css class for text_before)

class_after   => for all types                  (string - css class for text_after)

cols          => for textarea                   (integer)

disable       => for all types                  (true)

height        => for all types                  (integer)

maxlength     => for all types                  (integer)

name          => for all types                  (string)

placeholder   => for text, search, url, tel,    (string)
                 email, password, textarea

readonly      => for all types                  (true)

required      => for text, search, url, tel,    (true)
                 email, password, date pickers,
                 number, checkbox, radio,
                 textarea

rows          => for textarea                   (integer)

size          => for all types                  (int)

src           => for image                      (int)

style         => for all types                  (string - addes style attributes to value (internal) of textarea)

tabindex      => for all types                  (int)

text_before   => for all types                  (string - displays text prior to the input/textarea)

text_after    => for all types                  (string - displays text after the input/textarea)

style_after   => for all types                  (string - adds style attributes to text_after)

style_before  => for all types                  (string - adds style attributes to text_before)

value         => for all types                  (string - for textarea thsi will appear in the box)

width         => for all types                  (int)

wrap          => for textarea                   (string)
