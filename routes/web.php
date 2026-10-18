<?php

/** @var \App\Core\Router $router */

$router->add('GET', '/', 'AuthController@showLogin');
$router->add('POST', '/login/lockout-status', 'AuthController@loginLockoutStatus');
$router->add('POST', '/login', 'AuthController@login');
$router->add('GET', '/logout', 'AuthController@logout');

$router->add('GET', '/dashboard', 'DashboardController@index');

$router->add('GET', '/reports', 'ReportGenerationController@index');
$router->add('GET', '/reports/start/purchase-request', 'WorkflowController@startPurchaseRequest');
$router->add('GET', '/reports/start/disbursement-voucher', 'WorkflowController@startDisbursementVoucher');
$router->add('POST', '/reports/start/{type}', 'WorkflowController@startWorkflow');
$router->add('GET', '/reports/process/{id}/step/{step}', 'WorkflowController@showStep');
$router->add('POST', '/reports/process/{id}/step/{step}/save', 'WorkflowController@saveStep');
$router->add('POST', '/reports/process/{id}/step/{step}/clear', 'WorkflowController@clearStep');
$router->add('POST', '/reports/process/{id}/step/{step}/print', 'WorkflowController@printStep');
$router->add('POST', '/reports/process/{id}/next', 'WorkflowController@next');
$router->add('POST', '/reports/process/{id}/finish', 'WorkflowController@finish');
$router->add('GET', '/reports/process/{id}/complete', 'WorkflowController@complete');

$router->add('GET', '/archive', 'ArchiveController@index');
$router->add('GET', '/trash', 'TrashController@index');
$router->add('GET', '/trash/suggestions', 'TrashController@suggestions');
$router->add('POST', '/trash/{processId}/restore', 'TrashController@restore');
$router->add('POST', '/trash/{processId}/delete', 'TrashController@deletePermanently');
$router->add('GET', '/archive/suggestions', 'ArchiveController@suggestions');
$router->add('GET', '/archive/certification', 'ArchiveController@certification');
$router->add('POST', '/archive/certification/print', 'ArchiveController@printCertification');
$router->add('POST', '/archive/{processId}/delete', 'ArchiveController@delete');
$router->add('GET', '/system-input', 'SystemInputController@index');
$router->add('POST', '/system-input', 'SystemInputController@save');


