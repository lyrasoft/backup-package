# LYRASOFT 全站備份 Script

## Install

```
php -r "copy('https://raw.githubusercontent.com/lyrasoft/backup-script/master/install_script.php', 'backup_install_script.php');"
php backup_install_script.php
```

Will show:

```
Do you want to use DB? [Y/n]
Host[localhost]: 
DB Name: earth
User[root]: 
Password: 
Success install backup.php file.
Token: 9762fb7455a0a3282c0962f9e957b40c361de9bb
```

## 使用方式

### Web Backup

Copy the token, and call backup.php from URL:

```
https://(your site).com/backup.php?token=9762fb7455a0a3282c0962f9e957b40c361de9bb
```

### CLI

Backup to file.

```
php backup.php > /tmp/backup.zip
```

Backup with DB config:

```
php backup.php -u root -p {pass} --db={dbname} > /tmp/backup.zip
```

Show Token:

```
php backup.php token
```

Show Help

```
php backup.php -h
```
