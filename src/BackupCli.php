<?php

declare(strict_types=1);

namespace Lyrasoft\Backup;

use Symfony\Component\Console\Application;
use Windwalker\Utilities\Options\OptionAccessTrait;

class BackupCli extends Application
{
    use OptionAccessTrait;

    public function __construct(
        string $name = 'UNKNOWN',
        string $version = 'UNKNOWN',
        array $options = []
    ) {
        parent::__construct($name, $version);

        $this->options = $options;
    }
}
