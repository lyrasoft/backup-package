# LYRASOFT 全站備份 Script

必須搭配 Simular Watcher 備份系統使用。

## 使用方式

下載 `backup.php` 檔案到網站內任何位置，打開檔案並配置設定檔:

``` php
$options = array(
	/*
	 * Basic Information
	 * ------------------------------------------------------------
	 */
	'public_key' => '',
	'root'       => '/',

	/*
	 * The database information
	 * ------------------------------------------------------------
	 *
	 * Only support mysql now.
	 */
	'database' => array(
		'host' => 'localhost',
		'user' => '',
		'pass' => '',
		'name' => ''
	),
	
	/*
     * Ignore files of force included files.
     * ------------------------------------------------------------
     *
     * Ignore file:
     * /folder/to/ignore/*
     *
     * Force include
     * !/folder/to/retain.txt
     */
    'ignores' => array(
        '*/.git/*',
        '/logs/*',
        '!/logs/index.html',
        '/log/*',
        '!/log/index.html',
        '/cache/*',
        '!/cache/index.html',
        '/tmp/*',
        '!/tmp/index.html',
        '/administrator/components/com_akeeba/backup/*.zip',
    )
);
```

`public_key` 是 Simular Watcher 給的 Key

`root` 是你的網站根目錄相對於這個 Script 位置，如果 Script 放在跟目錄，就維持 `/` 或空字串即可，假設 Script 放在網站的 bin 目錄下，
則要往前一個層級 `'root' => '..',`

`database` 填寫該網站的 MySQL 資訊。

`ignores` 設定要排除或強制加入的檔案與目錄。
