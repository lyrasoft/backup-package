# LYRASOFT Site Backup Script

## Installation

### Windwalker 4

Install from composer.

```shell
composer create-project lyrasoft/backup
```

Then run this command to publish routes.

```shell
php windwalker pkg:install lyrasoft/backup --tag=routes
```

The config file is in `etc/packages/backup.php`

### Standalone

Install from composer

```php
composer create-project lyrasoft/backup
```

Then the installation script will ask you some questions:

```shell
Project Name: # Your Site Name, this will be the backup title
Do you want to dump Files? [y/N] # Mostly we can choose N.
Backup Root[.]: # Type the absolute or relative path to site root
Do you want to dump DB? [Y/n] # y
Host[localhost]: # DB host
DB Name: # DB name
User[root]: # DB user
Password: # DB password
Success install backup.php file.

# If you want to register to portal instantly, type "Y"
Register backup to portal? [Y/n]
Site URL: # Enter site URL, that portal can fetch backup file
Please fill XXX-XXX to Portal.
Open https://portal.simular.co/device/login from your local browser.
```

If you want to register to portal, see [Documentation](https://lyrasoft.atlassian.net/wiki/spaces/SRE/pages/629964827/Portal+php) 

After installed, the `config.php` file will be generated at `backup` root folder, you can modify it if you want.

## Commands

In windwalker, type

```shell
php windwalker backup:{command}
```

In standalone file, use:

```shell
php backup.php {command}
```

### Command: `run`

This command will instantly output the zip stream to terminal.

If you want to output to a file, use:

```shell
backup:run > /path/to/file.zip
```

You can enter your own db info:

```shell
backup:run --host=localhost --db=sakura -u=root -p {pass} > /path/to/file.zip
```

In windwalker, you may choose backup profile

```shell
php windwalker backup:run {profile} > /path/to/file.zip
```

### Command: `token`

If you want to get backup, use this command to print token string.

### Command: `register`

Register this backup script to Portal, see [Documentation](https://lyrasoft.atlassian.net/wiki/spaces/SRE/pages/629964827/Portal+php)
