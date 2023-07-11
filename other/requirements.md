# SMF Miniumn Requirements

## PHP
### PHP Version Support
| MIN SMF VERSION | MAX SMF VERSION | MIN PHP VERSION | MAX PHP VERSION |
| ------ | ------ | ------ | ------ |
| 2.1    | LATEST | 7.0.0  | 8.1.0  |

### Undocumented Version Support
- Versions below miniumn listed above are not supported
- Versions above the maxiumn listed above:
	- Will be supported in a future date unless this version has reached End of Life
	- Support is limited until then and provided as best effort
	- Git Repo may contain code testing support

### PHP INI
- engine directive must be set to On
- session.save_path directive must be set to a valid directory or empty
- file_uploads directive must be set to On
- upload_tmp_dir directive must be set to a valid directory or empty

### Additional PHP Libraires
- mbstring extension
- fileinfo extension

## Database Engine
### [MySQL](http://www.mysql.com)
- MySQL 5.6

### [PostgreSQL](http://www.postgresql.org)
- PostgreSQL 9.6
- standard_conforming_strings must be set to on

## Web Server
### Apache
- Apache Web Server 2.2 or 2.4 with mod_php

### FastCGI/FPM
- Any Web server capable of FastCGI supporting PHP FPM


# Recommenations

## PHP Recommenations
### Recommended PHP INI
- max_input_time set to a value of at least 30.
- post_max_size and upload_max_filesize set to the size of the largest attachments you wish to be able to - upload.
- memory_limit set to at least 512M.
- max_execution_time set to at least 15.
- session.use_trans_sid set to Off.

### Recommended PHP Libraires
- GD Graphics Library 2.0 or higher.

## Web Server
### Apache
- mod_security disabled (please see Mod security for more information).
