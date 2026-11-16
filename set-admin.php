<?php

require __DIR__.'/vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();

$user = $em->getRepository(\App\Entity\User::class)->find(1);

$user->setRoles(['ROLE_ADMIN']);
$em->flush();

echo "Admin role assigned!\n";