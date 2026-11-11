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
        ProjectUploadService $uploadService
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

        return $this->render('migration_project/show.html.twig', [
            'project' => $project,
            'uploadForm' => $form,
        ]);
    }

}
