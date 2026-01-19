<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/test')]
#[IsGranted('ROLE_ADMIN')]
class AdminTestController extends AbstractController
{
    #[Route('/creation', name: 'admin_test_creation')]
    public function testCreation(): Response
    {
        return $this->render('admin/test_creation.html.twig');
    }
}