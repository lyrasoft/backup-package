<?php
/**
 * LYRASOFT backup script.
 *
 * @copyright  Copyright (C) 2015 LYRASOFT. All rights reserved.
 */

// Uncomment if debugging
// error_reporting(32767);

set_time_limit(0);
ini_set('memory_limit', '1G');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

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
	'dump_database' => 1,

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
	),

	'config' => 'backup.json'
);

class BackupApplication
{
	/**
	 * @var  array
	 */
	protected $options = array();

	/**
	 * Class init
	 *
	 * @param array $options
	 */
	public function __construct(array $options)
	{
		$this->options = $options;

		// Override
		if (is_file(__DIR__ . '/' . $this->options['config']))
		{
			$override = json_decode(file_get_contents(__DIR__ . '/' . $this->options['config']), true);

			$this->options = array_merge($this->options, $override);
		}

		$this->options['root'] = realpath($path = __DIR__ . '/' . trim($this->getOption('root'), '/'));

		if (!is_dir($this->options['root']))
		{
			$this->close('Path: ' .$path . ' not exists');
		}
	}

	/**
	 * execute
	 *
	 * @return  void
	 */
	public function execute()
	{
		$this->authenticate();

		$path = $this->getOption('root') . '/tmp/watcher';

		$backupZipFile = new \SplFileInfo($path . '/' . $this->getBackupFilename() . '.zip');
		$backupSQLFile = new \SplFileInfo($path . '/' . $this->getBackupFilename() . '.sql');

		// create folder
		if (!is_dir($path))
		{
			if (!mkdir($path, 0755, true))
			{
				$this->close('Make dir ' . $path . ' fail.');
			}
		}

		$this->writeHtaccess($backupZipFile->getPath() . '/.htaccess');

		if ($this->getOption('dump_database', true))
		{
			$this->dumpSQL($backupSQLFile);
		}

		$this->zipFiles($backupZipFile, $backupSQLFile);

		$this->download($backupZipFile);
	}

	/**
	 * dumpSQL
	 *
	 * @param SplFileInfo $backupSQLFile
	 *
	 * @return  void
	 */
	protected function dumpSQL(\SplFileInfo $backupSQLFile)
	{
		// Delete old file
		if (is_file($backupSQLFile->getPathname()))
		{
			unlink($backupSQLFile->getPathname());
		}

		try
		{
			$sql = DatabaseDumper::dump($this->getOption('database', array()));
		}
		catch (RuntimeException $e)
		{
			$this->close($e->getMessage());
		}

		file_put_contents($backupSQLFile->getPathname(), $sql);
	}

	/**
	 * zipFiles
	 *
	 * @param SplFileInfo $file
	 * @param SplFileInfo $sqlFile
	 *
	 * @return bool
	 */
	protected function zipFiles(\SplFileInfo $file, \SplFileInfo $sqlFile)
	{
		// Delete old file
		if (is_file($file->getPathname()))
		{
			@unlink($file->getPathname());
		}

		$zip = new ZipArchive;

		if ($zip->open($file->getPathname(), ZipArchive::CREATE) === true)
		{
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->getOption('root'), RecursiveDirectoryIterator::SKIP_DOTS));

			$filter = new FileFilter($this->getOption('ignores'), $this->getOption('root'));

			/** @var \SplFileInfo $item */
			foreach ($iterator as $item)
			{
				// Excludes
				if ($filter->test($item->getPathname()))
				{
					continue;
				}

				$dest = str_replace($this->getOption('root') . DIRECTORY_SEPARATOR, '', $item->getPathname());

				if ($item->isDir())
				{
					$zip->addEmptyDir($dest);

					continue;
				}

				$zip->addFile($item->getPathname(), $dest);
			}

			if ($this->getOption('dump_database', true))
			{
				$zip->addFile($sqlFile->getPathname(), $sqlFile->getBasename());
			}
		}

		return $zip->close();
	}

	/**
	 * download
	 *
	 * @param SplFileInfo $file
	 *
	 * @return  void
	 */
	protected function download(\SplFileInfo $file)
	{
		if (!is_file($file->getPathname()))
		{
			$this->close('No file to download', 404);
		}

		$info = pathinfo($file->getPathname());

		$filesize = filesize($file->getPathname());

		// Set Header
		header('Content-Type: application/octet-stream');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: pre-check=0, post-check=0, max-age=0');
		header('Content-Transfer-Encoding: binary');
		header('Content-Encoding: none');
		header('Content-type: application/force-download');
		header('Content-length: ' . $filesize);
		header('Content-Disposition: attachment; filename="' . $info['basename'] . '"');

		$handle    = fopen($file->getPathname(), 'rb');
		$chunksize = 1 * (1024 * 1024);

		// Start Download File by Stream
		while (!feof($handle))
		{
			$buffer = fread($handle, $chunksize);
			echo $buffer;
			// ob_flush();
			flush();
		}

		fclose($handle);

		http_response_code(200);

		exit();
	}

	/**
	 * writeHtaccess
	 *
	 * @param string $dest
	 *
	 * @return  void
	 */
	protected function writeHtaccess($dest)
	{
		if (is_file($dest))
		{
			return;
		}

		$htaccess = <<<HT
<IfModule !mod_authz_core.c>
Order deny,allow
Deny from all
</IfModule>
<IfModule mod_authz_core.c>
  <RequireAll>
    Require all denied
  </RequireAll>
</IfModule>
HT;

		file_put_contents($dest, $htaccess);
	}

	/**
	 * authenticate
	 *
	 * @return  boolean
	 */
	public function authenticate()
	{
		$token = isset($_REQUEST['access_token']) ? $_REQUEST['access_token'] : $this->close('Invalid Token');

		$key = $this->getOption('public_key') ? $this->getOption('public_key') : $this->close('No public Key');

		if (sha1(md5('SimularWatcher' . $token)) !== $key)
		{
			return $this->close('Invalid Token');
		}

		return true;
	}

	/**
	 * getOption
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return  mixed
	 */
	public function getOption($key, $default = null)
	{
		if (!isset($this->options[$key]))
		{
			return $default;
		}

		return $this->options[$key];
	}

	/**
	 * close
	 *
	 * @param string $msg
	 * @param int    $code
	 *
	 * @return  boolean
	 */
	public function close($msg, $code = 401)
	{
		http_response_code($code);

		return exit($msg);
	}

	/**
	 * getBackupFilename
	 *
	 * @return  string
	 */
	public function getBackupFilename()
	{
		if (!isset($_SERVER['HTTP_HOST']))
		{
			return 'backup';
		}

		$base = $_SERVER['HTTP_HOST'] . '/' . $_SERVER['SCRIPT_NAME'];

		$str = str_replace('-', ' ', $base);

		if (function_exists('mb_strtolower'))
		{
			$str = trim(mb_strtolower($str));
		}
		else
		{
			$str = trim(strtolower($str));
		}

		$str = preg_replace('/(\s|[^A-Za-z0-9\-])+/', '-', $str);

		return trim($str, '-');
	}

	/**
	 * registerErrorHandler
	 *
	 * @return  void
	 */
	public static function registerErrorHandler()
	{
		set_error_handler(function($errno, $errstr, $errfile, $errline)
		{
			throw new ErrorException($errstr, $errno, 1, $errfile, $errline);
		});
	}
}

/**
 * The FileFilter class.
 */
class FileFilter
{
	/**
	 * @var  array
	 */
	protected $rules = array();

	/**
	 * @var  string
	 */
	protected $root = __DIR__;

	/**
	 * Class init.
	 *
	 * @param array  $rules
	 * @param string $root
	 */
	public function __construct(array $rules, $root = __DIR__)
	{
		$this->rules = $rules;

		foreach ($this->rules as &$rule)
		{
			$rule = str_replace(array('/', '\\'), '/', $rule);
		}

		$this->root = $root;
	}

	/**
	 * test
	 *
	 * @param string $string
	 *
	 * @return  boolean
	 */
	public function test($string)
	{
		$match = false;

		// fnmatch() only work for UNIX file path
		$string = str_replace(array('/', '\\'), '/', $string);

		$string = substr($string, strlen(rtrim($this->root, '/')));

		foreach ($this->rules as $rule)
		{
			// Negative
			if (substr($rule, 0, 1) == '!')
			{
				$rule = substr($rule, 1);

				if (fnmatch($rule, $string))
				{
					$match = false;
				}
			}
			// Normal
			else
			{
				if (fnmatch($rule, $string))
				{
					$match = true;
				}
			}
		}

		return $match;
	}
}

/**
 * The DatabaseDumper class.
 */
class DatabaseDumper
{
	/**
	 * @var  mysqli
	 */
	protected static $db;

	/**
	 * dump
	 *
	 * @param array $options
	 *
	 * @return  string
	 */
	public static function dump(array $options)
	{
		$options = array_merge(array(
			'host' => '',
			'user' => '',
			'pass' => '',
			'name' => ''
		), $options);

		static::$db = new mysqli(
			$options['host'],
			$options['user'],
			$options['pass'],
			$options['name']
		);

		static::$db->set_charset("utf8");

		// Get tables
		$stat = static::$db->query('SHOW TABLES');

		$sql = array();

		while ($result = $stat->fetch_row())
		{
			$create = static::$db->query('SHOW CREATE TABLE ' . $result[0])->fetch_row();

			$sql[] = 'DROP TABLE IF EXISTS ' . $result[0];
			$sql[] = $create[1];

			static::exportRows($result[0], $sql);
		}

		return implode(";\n", $sql) . ';';
	}

	/**
	 * exportRows
	 *
	 * @param string $table
	 * @param array  $sql
	 *
	 * @return  void
	 */
	public static function exportRows($table, &$sql)
	{
		$stat = static::$db->query('SELECT * FROM ' . $table);

		$query = 'INSERT ' . $table . ' VALUES (%s)';

		while ($row = $stat->fetch_object())
		{
			$values = array();

			foreach (get_object_vars($row) as $k => $v)
			{
				$values[] = "'" . static::$db->real_escape_string($v) . "'";
			}

			$sql[] = sprintf($query, implode(', ', $values));
		}
	}
}

// Set error handler
BackupApplication::registerErrorHandler();

$app = new BackupApplication($options);

try
{
	$app->execute();
}
catch (Exception $e)
{
	http_response_code($e->getCode());
	echo $e->getMessage();
}
