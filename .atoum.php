<?php

use \atoum\atoum;

$report = $script->addDefaultReport();
$coverageField = new atoum\report\fields\runner\coverage\html('Ting', __DIR__ . '/tests/coverage/');
$script->noCodeCoverageForClasses('Symfony\Component\Validator\Constraint', 'Symfony\Component\Validator\ConstraintValidator', 'Symfony\Component\DependencyInjection\Extension\Extension', 'Symfony\Component\HttpKernel\DependencyInjection\Extension');
$coverageField->setRootUrl('file://' . __DIR__ . '/tests/coverage/index.html');
$report->addField($coverageField);
$runner->addTestsFromDirectory('tests/units');
