<?php

/**
 * Part of backup-script project.
 *
 * @copyright  Copyright (C) 2022 __ORGANIZATION__.
 * @license    __LICENSE__
 */

declare(strict_types=1);

exec('git clone https://github.com/lyrasoft/backup-script.git backup');

chdir('backup');

exec('composer update');
exec('php install_script.php');
