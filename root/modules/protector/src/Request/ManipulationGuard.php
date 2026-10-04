<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Config\ConfigStore;
use ImpressCMS\Module\Protector\Filter\FilterHandler;
use ImpressCMS\Module\Protector\Http\Responder;

final class ManipulationGuard
{
    public function __construct(
        private readonly ConfigStore $config,
        private readonly FilterHandler $filters,
        private readonly Responder $responder,
    ) {
    }

    public function check(): void
    {
        $index = ICMS_ROOT_PATH . '/index.php';

        if ((string) ($_SERVER['SCRIPT_FILENAME'] ?? '') !== $index) {
            return;
        }

        $rootStat = stat(ICMS_ROOT_PATH);
        $indexStat = stat($index);
        $fingerprint = "{$rootStat['mtime']}:{$indexStat['mtime']}:{$indexStat['ino']}";
        $known = $this->config->current()->string('manip_value');

        if (!$this->config->current()->enabled('manip_value')) {
            $this->config->update('manip_value', $fingerprint);

            return;
        }

        if ($fingerprint === $known) {
            return;
        }

        if ($this->filters->execute('postcommon_manipu') === 0) {
            $this->responder->halt('Protector detects site manipulation.');
        }

        $this->config->update('manip_value', $fingerprint);
    }
}
