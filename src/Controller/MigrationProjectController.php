<?php

namespace App\Controller;

use App\Config\StatusType;
use App\Entity\MigrationProject;
use App\Form\MigrationProjectType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\ProjectUploadType;
use App\Service\Project\ProjectUploadService;
use App\Service\Project\ProjectExtractionService;
use App\Service\Analysis\PhpFileScannerService;
use App\Service\Migration\MigrationPlanGeneratorService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Service\Migration\MigrationPlanReaderService;
use App\Service\Migration\PhpCodeMigrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Service\Project\ProjectSourceFileService;
use App\Service\Analysis\FrameworkDetectorService;

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
                'uid' => $project->getUid()->toRfc4122(),
            ]);
        }

        return $this->render('migration_project/new.html.twig', [
            'form' => $form,
        ]);
    }


    #[Route('/projects/{uid}', name: 'app_project_show')]
    public function show(
        #[MapEntity(mapping: ['uid' => 'uid'])]
        MigrationProject $project,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectUploadService $uploadService,
        PhpFileScannerService $scanner,
        MigrationPlanReaderService $planReader,
        FrameworkDetectorService $frameworkDetector,
    ): Response {
        $form = $this->createForm(ProjectUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('archive')->getData();

            try {
                $filename = $uploadService->upload($file, $project);

                $project->setArchiveFilename($filename);
                $project->setStatus(StatusType::UPLOADED);

                $entityManager->flush();

                $this->addFlash('success', 'ZIP file uploaded successfully.');

                return $this->redirectToRoute('app_project_show', [
                    'uid' => $project->getUid()->toRfc4122(),
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        $migrationPlan = $planReader->read($project);

        $analysis = null;
        $detectedFramework = null;

        if ($project->getStatus() === StatusType::EXTRACTED) {
            $analysis = $scanner->scanProject($project);

            $extractedPath = sprintf(
                '%s/var/storage/projects/%d/extracted',
                $this->getParameter('kernel.project_dir'),
                $project->getId()
            );

            $detectedFramework = $frameworkDetector->detect($extractedPath);
}

        $migrationPlanHtml = $migrationPlan
            ? $planReader->renderHtml($migrationPlan)
            : null;

        return $this->render('migration_project/show.html.twig', [
            'project' => $project,
            'uploadForm' => $form,
            'analysis' => $analysis,
            'migrationPlan' => $migrationPlan,
            'migrationPlanHtml' => $migrationPlanHtml,
            'detectedFramework' => $detectedFramework,
        ]);
    }

    #[Route('/projects/{uid}/extract', name: 'app_project_extract', methods: ['POST'])]
    public function extract(
        #[MapEntity(mapping: ['uid' => 'uid'])]
        MigrationProject $project,
        Request $request,
        ProjectExtractionService $extractionService,
        FrameworkDetectorService $frameworkDetector,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid(
            'extract' . $project->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $extractedPath = $extractionService->extract($project);

            $detected = $frameworkDetector->detect($extractedPath);

            $project->setStatus(StatusType::EXTRACTED);
            $entityManager->flush();

            $this->addFlash(
                'success',
                sprintf(
                    'ZIP extracted successfully. Detected framework: %s',
                    $detected['framework']
                )
            );
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_project_show', [
            'uid' => $project->getUid()->toRfc4122(),
        ]);
    }


    #[Route('/projects/{uid}/generate-plan', name: 'app_project_generate_plan', methods: ['POST'])]
    public function generatePlan(
        #[MapEntity(mapping: ['uid' => 'uid'])]
        MigrationProject $project,
        Request $request,
        PhpFileScannerService $scanner,
        MigrationPlanGeneratorService $planGenerator
    ): Response {
        if (!$this->isCsrfTokenValid('generate-plan' . $project->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($project->getStatus() !== StatusType::EXTRACTED) {
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
                'uid' => $project->getUid()->toRfc4122(),
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
            'uid' => $project->getUid()->toRfc4122(),
        ]);
    }

    #[Route('/projects/{uid}/migrate-code', name: 'app_project_migrate_code', methods: ['POST'])]
    public function migrateCode(
        #[MapEntity(mapping: ['uid' => 'uid'])]
        MigrationProject $project,
        Request $request,
        ProjectSourceFileService $sourceFileService,
        PhpCodeMigrationService $codeMigrationService
    ): JsonResponse {
        try {
            $this->validateMigrationRequest($project, $request);

            $selectedFile = $sourceFileService->getSelectedPhpFile(
                $project,
                $request->request->get('file')
            );

            $result = $codeMigrationService->migrate(
                $selectedFile['sourceCode'],
                $selectedFile['path'],
                $selectedFile['analysis'],
                $selectedFile['dependencyContext']
            );

            if (trim($result) === '') {
                throw new \RuntimeException('AI returned an empty response.');
            }

            return $this->json([
                'file' => $selectedFile['path'],
                'sourceCode' => $selectedFile['sourceCode'],
                'generatedCode' => $result,
            ]);
        } catch (HttpExceptionInterface $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Code migration failed. Check the application logs.',
            ], 500);
        }
    }

    private function validateMigrationRequest(
        MigrationProject $project,
        Request $request
    ): void {
        if (!$this->isCsrfTokenValid(
            'migrate-code' . $project->getId(),
            $request->request->get('_token')
        )) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        if ($project->getStatus() !== StatusType::EXTRACTED) {
            throw new AccessDeniedHttpException('Project must be extracted first.');
        }
    }

}
