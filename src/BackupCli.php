<?php

declare(strict_types=1);

namespace Lyrasoft\Backup;

use Symfony\Component\Console\Application;
use Windwalker\Utilities\Options\OptionAccessTrait;

class BackupCli extends Application
{
    public function __construct(
        string $name = 'UNKNOWN',
        string $version = 'UNKNOWN',
        protected array $options = []
    ) {
        parent::__construct($name, $version);

        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }
}
