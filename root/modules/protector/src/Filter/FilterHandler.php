<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Filter;

use ImpressCMS\Module\Protector\Config\ConfigStore;
use ImpressCMS\Module\Protector\Http\Responder;

class FilterHandler
{
    public function __construct(
        private readonly ConfigStore $config,
        private readonly Responder $responder,
        private readonly string $moduleDirectory,
    ) {
    }

    public function run(string $type, string $dyingMessage = ''): int
    {
        $result = $this->execute($type);

        if ($result === 0 && $dyingMessage !== '') {
            $this->responder->halt($dyingMessage);
        }

        return $result;
    }

    public function execute(string $type): int
    {
        $result = 0;

        foreach ($this->filesFor($type) as $filter) {
            include_once "{$filter['base']}/{$filter['file']}";
            $name = 'protector_' . substr($filter['file'], 0, -4);

            if (function_exists($name)) {
                $result |= (int) call_user_func($name);
                continue;
            }

            if (class_exists($name)) {
                $result |= (int) (new $name())->execute();
            }
        }

        return $result;
    }

    private function isPlainFilterFile(string $file): bool
    {
        return preg_match('/^[A-Za-z0-9_]+\.php$/', $file) === 1;
    }

    /** @return array<int, array{file: string, base: string}> */
    private function filesFor(string $type): array
    {
        $prefix = "{$type}_";
        $filters = [];

        foreach (preg_split('/[\s\n,]+/', $this->config->current()->string('filters')) as $file) {
            if (substr($file, -4) !== '.php') {
                $file .= '.php';
            }

            if (str_starts_with($file, $prefix) && $this->isPlainFilterFile($file)) {
                $filters[] = ['file' => $file, 'base' => "{$this->moduleDirectory}/filters_byconfig"];
            }
        }

        $enabled = "{$this->moduleDirectory}/filters_enabled";
        $handle = opendir($enabled);

        while (($file = readdir($handle)) !== false) {
            if (str_starts_with($file, $prefix) && $this->isPlainFilterFile($file)) {
                $filters[] = ['file' => $file, 'base' => $enabled];
            }
        }

        closedir($handle);

        return $filters;
    }
}

class_alias(FilterHandler::class, 'ProtectorFilterHandler');
