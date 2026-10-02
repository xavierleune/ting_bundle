<?php

use \atoum\atoum;

$report = $script->addDefaultReport();
$coverageField = new atoum\report\fields\runner\coverage\html('Ting', __DIR__ . '/tests/coverage/');
$script->noCodeCoverageForClasses('Symfony\Component\Validator\Constraint', 'Symfony\Component\Validator\ConstraintValidator', 'Symfony\Component\DependencyInjection\Extension\Extension', 'Symfony\Component\HttpKernel\DependencyInjection\Extension');
$coverageField->setRootUrl('file://' . __DIR__ . '/tests/coverage/index.html');
$report->addField($coverageField);
/**
 * @var $runner \atoum\atoum\scripts\runner
 */
$testsDirectory = __DIR__ . '/tests/units/TingBundle';
$subDirectories = glob($testsDirectory . '/*', GLOB_ONLYDIR);
$files = glob($testsDirectory . '/*.php');

foreach ($subDirectories as $directory) {
    $runner->addTestsFromPattern($directory . '/*');
}
foreach ($files as $file) {
    $runner->addTestsFromPattern($files);
}
