<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\GitHubAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoLoginController extends AbstractController
{
    #[Route('/demo-login', name: 'app_demo_login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $entityManager,
        Security $security,
        #[Autowire('%kernel.environment%')] string $environment,
    ): Response {
        if ($environment !== 'dev') {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('demo_login', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $repository = $entityManager->getRepository(User::class);

        $user = $repository->findOneBy([
            'githubId' => 'demo-local-user',
        ]);

        if (!$user) {
            $user = new User();
            $user->setGithubId('demo-local-user');
            $user->setUsername('Demo User');
            $user->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();
        }

        $security->login(
            $user,
            GitHubAuthenticator::class,
            'main'
        );

        return $this->redirectToRoute('app_home');
    }
}