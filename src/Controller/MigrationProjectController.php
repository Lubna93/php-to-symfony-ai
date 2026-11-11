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
use App\Service\MigrationPlanGeneratorService;
use League\CommonMark\CommonMarkConverter;

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

        $planPath = sprintf(
            '%s/var/storage/projects/%d/migration-plan.md',
            $this->getParameter('kernel.project_dir'),
            $project->getId()
        );

        $migrationPlan = is_file($planPath)
            ? file_get_contents($planPath)
            : null;

        if ($project->getStatus() === 'extracted') {
            $directory = sprintf(
                '%s/var/storage/projects/%d/extracted',
                $this->getParameter('kernel.project_dir'),
                $project->getId()
            );

            $analysis = $scanner->scan($directory);
        }

        $migrationPlanHtml = null;

        if ($migrationPlan) {
            $converter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);

            $migrationPlanHtml = $converter->convert($migrationPlan)->getContent();
        }

        return $this->render('migration_project/show.html.twig', [
            'project' => $project,
            'uploadForm' => $form,
            'analysis' => $analysis,
            'migrationPlan' => $migrationPlan,
            'migrationPlanHtml' => $migrationPlanHtml,
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


    #[Route('/projects/{id}/generate-plan', name: 'app_project_generate_plan', methods: ['POST'])]
    public function generatePlan(
        MigrationProject $project,
        Request $request,
        PhpFileScannerService $scanner,
        MigrationPlanGeneratorService $planGenerator
    ): Response {
        if (!$this->isCsrfTokenValid('generate-plan' . $project->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($project->getStatus() !== 'extracted') {
            throw $this->createAccessDeniedException('Project must be extracted first.');
        }

        $projectDirectory = sprintf(
            '%s/var/storage/projects/%d',
            $this->getParameter('kernel.project_dir'),
            $project->getId()
        );

        $analysis = $scanner->scan($projectDirectory . '/extracted');

        if ($analysis['totalFiles'] === 0) {
            $this->addFlash('danger', 'No PHP files found to analyze.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
            ]);
        }

        try {
            $plan = $planGenerator->generate($analysis);

            if (!is_string($plan) || trim($plan) === '') {
                throw new \RuntimeException('The AI returned an empty migration plan.');
            }

            file_put_contents(
                $projectDirectory . '/migration-plan.md',
                $plan,
                LOCK_EX
            );

            $this->addFlash('success', 'Migration plan generated successfully.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Migration plan generation failed. Check the application logs.');
        }

        return $this->redirectToRoute('app_project_show', [
            'id' => $project->getId(),
        ]);
    }

}
