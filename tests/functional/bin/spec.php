<?php

declare(strict_types=1);

use ImpressCMS\Module\Protector\Tests\Functional\KnownDefect;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$testDirectory = dirname(__DIR__) . '/tests';
$files = glob($testDirectory . '/*Test.php') ?: [];
sort($files);

$before = get_declared_classes();

foreach ($files as $file) {
    require_once $file;
}

$classes = array_values(array_filter(
    array_diff(get_declared_classes(), $before),
    static fn (string $class): bool => str_ends_with($class, 'Test'),
));

$rows = [];
$byDefect = [];

foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);
    $area = trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace('Test', '', $reflection->getShortName())));

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        foreach ($method->getAttributes(Scenario::class) as $attribute) {
            /** @var Scenario $scenario */
            $scenario = $attribute->newInstance();
            $defects = array_map(static fn (ReflectionAttribute $a): KnownDefect => $a->newInstance(), $method->getAttributes(KnownDefect::class));

            $rows[$area][] = ['scenario' => $scenario, 'defects' => $defects, 'method' => $method->getName()];

            foreach ($defects as $defect) {
                $byDefect[$defect->defect]['note'] = $defect->note;
                $byDefect[$defect->defect]['ids'][] = $scenario->id;
            }
        }
    }
}

$escape = static fn (string $text): string => str_replace(['|', "\n"], ['\\|', ' '], $text);

$intro = (string) file_get_contents(dirname(__DIR__) . '/SPEC.intro.md');
$output = $intro . "\n";

$total = 0;

foreach ($rows as $area => $areaRows) {
    $output .= "## {$area}\n\n";
    $output .= "| ID | Given | When | Then | Known defect |\n|---|---|---|---|---|\n";

    foreach ($areaRows as $row) {
        $total++;
        $scenario = $row['scenario'];
        $flag = $row['defects'] === [] ? '' : implode(', ', array_map(static fn (KnownDefect $d): string => '**' . $d->defect . '**', $row['defects']));
        $output .= sprintf(
            "| %s | %s | %s | %s | %s |\n",
            $scenario->id,
            $escape($scenario->given),
            $escape($scenario->when),
            $escape($scenario->then),
            $flag,
        );
    }

    $output .= "\n";
}

$output .= "## Known-defect index\n\n";
$output .= "These scenarios assert today's behaviour although it is a defect. They are the only assertions that may change later, each in the phase that fixes the defect.\n\n";
$output .= "| Defect | Pinned behaviour | Scenarios |\n|---|---|---|\n";

uksort($byDefect, static fn (string $a, string $b): int => [$a[0], (int) substr($a, 1)] <=> [$b[0], (int) substr($b, 1)]);

foreach ($byDefect as $id => $info) {
    $output .= sprintf("| **%s** | %s | %s |\n", $id, $escape($info['note']), implode(', ', $info['ids']));
}

$output .= "\n_{$total} scenarios generated from the test attributes by `php tests/functional/bin/spec.php`._\n";

file_put_contents(dirname(__DIR__) . '/SPEC.md', $output);
fwrite(STDOUT, "SPEC.md written: {$total} scenarios in " . count($rows) . " areas\n");
