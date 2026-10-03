<?php
require __DIR__.'/php-boundaries.php';
function assertBoundary(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$dependencies = phpDependencies('<?php namespace App\\Modules\\A; use App\\Modules\\B\\{Contracts\\Read as Reader, Models\\Record}; class Test { public function run(Reader $reader): \\App\\Modules\\C\\Data\\Result { return new \\App\\Modules\\C\\Data\\Result; } }');
assertBoundary(in_array('App\\Modules\\B\\Contracts\\Read', $dependencies), 'Grouped alias resolved');
assertBoundary(in_array('App\\Modules\\B\\Models\\Record', $dependencies), 'Unused grouped private import detected');
assertBoundary(in_array('App\\Modules\\C\\Data\\Result', $dependencies), 'Fully qualified reference detected');
$public = ['app/Modules/A/Actions/Read.php' => '<?php use App\\Modules\\B\\Contracts\\Read;'];
assertBoundary(phpViolations($public) === [], 'Public boundary accepted');
$private = phpViolations(['app/Modules/A/Actions/Read.php' => '<?php use App\\Modules\\B\\Models\\Record;']);
assertBoundary(count($private) === 1 && $private[0]['rule'] === 'php-private-module', 'Private model rejected');
$cycle = phpViolations($public + ['app/Modules/B/Actions/Read.php' => '<?php use App\\Modules\\A\\Contracts\\Read;']);
assertBoundary(count($cycle) === 2 && $cycle[0]['rule'] === 'php-module-cycle', 'Public contract cycle rejected');
echo "PHP architecture fixtures passed.\n";
