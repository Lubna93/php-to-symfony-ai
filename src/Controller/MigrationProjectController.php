<?php

namespace App\Controller;

use App\Entity\MigrationProject;
use App\Form\MigrationProjectType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\ProjectUploadType;
use App\Service\ProjectUploadService;
use App\Service\ProjectExtractionService;
use App\Service\PhpFileScannerService;

class MigrationProjectController extends AbstractController
{
    #[Route('/projects/new', name: 'app_project_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $project = new MigrationProject();

        $form = $this->createForm(MigrationProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($project);
            $entityManager->flush();

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        return $this->render('migration_project/new.html.twig', [
            'form' => $form,
        ]);
    }


    #[Route('/projects/{id}', name: 'app_project_show', requirements: ['id' => '\d+'])]
    public function show(
        MigrationProject $project,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectUploadService $uploadService,
        PhpFileScannerService $scanner
    ): Response {
        $form = $this->createForm(ProjectUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('archive')->getData();

            try {
                $filename = $uploadService->upload($file, $project);

                $project->setArchiveFilename($filename);
                $project->setStatus('uploaded');

                $entityManager->flush();

                $this->addFlash('success', 'ZIP file uploaded successfully.');

                return $this->redirectToRoute('app_project_show', [
                    'id' => $project->getId(),
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        $analysis = null;

        if ($project->getStatus() === 'extracted') {
            $directory = sprintf(
                '%s/var/storage/projects/%d/extracted',
                $this->getParameter('kernel.project_dir'),
                $project->getId()
            );

            $analysis = $scanner->scan($directory);
        }

        return $this->render('migration_project/show.html.twig', [
            'project' => $project,
            'uploadForm' => $form,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/projects/{id}/extract', name: 'app_project_extract', methods: ['POST'])]
    public function extract(
        MigrationProject $project,
        Request $request,
        ProjectExtractionService $extractionService,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('extract' . $project->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $extractionService->extract($project);

            $project->setStatus('extracted');
            $entityManager->flush();

            $this->addFlash('success', 'ZIP extracted successfully.');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_project_show', [
            'id' => $project->getId(),
        ]);
    }

}
