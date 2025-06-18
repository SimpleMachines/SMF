# SMF Miniumn Requirements

## PHP
### PHP Version Support
| MIN SMF VERSION | MAX SMF VERSION | MIN PHP VERSION | MAX PHP VERSION |
| --------------- | --------------- | --------------- | --------------- |
| 3.0             | LATEST          | 8.0.0           | LATEST          |

### Undocumented Version Support
- Versions below miniumn listed above are not supported.
- Versions above the maxiumn listed above:
	- Will be supported in a future date unless this version has reached End of Life.
	- Support is limited until then and provided as best effort.
	- Git repository may contain code that tests support for higher PHP versions.

### PHP INI
- engine directive must be set to On.
- session.save_path directive must be set to a valid directory or empty.
- file_uploads directive must be set to On.
- upload_tmp_dir directive must be set to a valid directory or empty.

### PHP Extensions and Libraries
- Multibyte String extension.
- Fileinfo extension.
- MySQLi extension or PostgreSQL extension (depending on database engine used).

## Database Engine
### [MySQL](http://www.mysql.com)
- MySQL 8.0.35

### [PostgreSQL](http://www.postgresql.org)
- PostgreSQL 12.17
- standard_conforming_strings must be set to on.

## Web Server
### Apache
- Apache Web Server 2.2 or 2.4 with mod_php.

### FastCGI/FPM
- Any Web server capable of FastCGI supporting PHP FPM.


# Recommenations

## PHP
### PHP INI
- max_input_time set to a value of at least 30.
- post_max_size and upload_max_filesize set to the size of the largest attachments you wish to be able to - upload.
- memory_limit set to at least 512M.
- max_execution_time set to at least 15.
- session.use_trans_sid set to Off.

### PHP Extensions and Libraries
- GD Graphics Library 2.0 or higher and the gd extension.
- ICU Library 68.1 or higher and the intl extension.
- Libcurl Library 7.29.0 or higher and the cURL extension.
- Exif extension.
- FTP extension.
- Libxml Library 2.6.0 or higher and the libxml extension.
- Libxslt Library 1.1.0 or higher and the XSL extension.

## Web Server
### Apache
- mod_security disabled (please see Mod security for more information).
