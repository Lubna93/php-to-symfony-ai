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
use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\MigrationProjectRepository;
use App\Service\Migration\Generator\ProjectGenerationManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

#[IsGranted('ROLE_USER')]
class MigrationProjectController extends AbstractController
{
    #[Route('/projects', name: 'app_project_index', methods: ['GET'])]
    public function index(
        MigrationProjectRepository $projectRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $projects = $projectRepository->findBy(
            ['owner' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('migration_project/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/projects/new', name: 'app_project_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $project = new MigrationProject();

        /** @var User $user */
        $user = $this->getUser();
        $project->setOwner($user);

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
        $this->denyAccessUnlessOwner($project);
        $form = $this->createForm(ProjectUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('archive')->getData();

            try {
                $filename = $uploadService->upload($file, $project);
                $this->clearGeneratedArchive($project);
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

        $generatedZipPath = sprintf(
            '%s/var/storage/projects/%d/symfony-generated.zip',
            $this->getParameter('kernel.project_dir'),
            $project->getId()
        );

        $hasGeneratedArchive = is_file($generatedZipPath);

        return $this->render('migration_project/show.html.twig', [
            'project' => $project,
            'uploadForm' => $form,
            'analysis' => $analysis,
            'migrationPlan' => $migrationPlan,
            'migrationPlanHtml' => $migrationPlanHtml,
            'detectedFramework' => $detectedFramework,
            'hasGeneratedArchive' => $hasGeneratedArchive,
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
        $this->denyAccessUnlessOwner($project);

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
        $this->denyAccessUnlessOwner($project);

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
            $plan = $planGenerator->generate(
                $analysis,
                $project->getSourceFramework(),
                $project->getTargetFramework()
            );

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
            $this->denyAccessUnlessOwner($project);
            $this->validateMigrationRequest($project, $request);

            $selectedFile = $sourceFileService->getSelectedPhpFile(
                $project,
                $request->request->get('file')
            );

            $result = $codeMigrationService->migrate(
                $selectedFile['sourceCode'],
                $selectedFile['path'],
                $selectedFile['analysis'],
                $selectedFile['dependencyContext'],
                $project->getSourceFramework(),
                $project->getTargetFramework()
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

    #[Route('/projects/{uid}/generate-archive', name: 'app_project_generate_archive', methods: ['POST'])]
    public function generateArchive(
        #[MapEntity(mapping: ['uid' => 'uid'])]
        MigrationProject $project,
        Request $request,
        ProjectGenerationManager $generationManager,
        LoggerInterface $logger
    ): Response {
        $this->denyAccessUnlessOwner($project);

        if (!$this->isCsrfTokenValid(
            'generate-archive' . $project->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($project->getStatus() !== StatusType::EXTRACTED) {
            throw $this->createAccessDeniedException('Project must be extracted first.');
        }

        if ($project->getSourceFramework() !== 'Plain PHP') {
            throw $this->createAccessDeniedException('Unsupported source framework.');
        }

        $outputDirectory = sprintf(
            '%s/var/storage/projects/%d',
            $this->getParameter('kernel.project_dir'),
            $project->getId()
        );

        try {
            $generationManager->generateArchive(
                $project,
                $outputDirectory,
                regenerate: true
            );

            $this->addFlash('success', 'Symfony files generated successfully.');

        } catch (\Throwable $e) {
            $logger->error('Project generation failed', [
                'projectId' => $project->getId(),
                'exception' => $e,
            ]);

            $this->addFlash(
                'danger',
                'Project generation failed. Check the application logs.'
            );
        }
        return $this->redirectToRoute('app_project_show', [
            'uid' => $project->getUid()->toRfc4122(),
        ]);
    }

    private function clearGeneratedArchive(MigrationProject $project): void
    {
        $projectDirectory = sprintf(
            '%s/var/storage/projects/%d',
            $this->getParameter('kernel.project_dir'),
            $project->getId()
        );

        $filesystem = new Filesystem();

        $filesystem->remove([
            $projectDirectory . '/symfony-generated.zip',
            $projectDirectory . '/generated',
        ]);
    }

    #[Route('/projects/{uid}/download-archive', name: 'app_project_download_archive', methods: ['GET'])]
    public function downloadArchive(
        #[MapEntity(mapping: ['uid' => 'uid'])]
        MigrationProject $project
    ): BinaryFileResponse {
        $this->denyAccessUnlessOwner($project);

        $zipPath = sprintf(
            '%s/var/storage/projects/%d/symfony-generated.zip',
            $this->getParameter('kernel.project_dir'),
            $project->getId()
        );

        if (!is_file($zipPath)) {
            throw $this->createNotFoundException('Generated archive not found.');
        }

        return $this->file(
            $zipPath,
            'symfony-generated.zip',
            ResponseHeaderBag::DISPOSITION_ATTACHMENT
        );
    }

    private function denyAccessUnlessOwner(MigrationProject $project): void
    {
        $user = $this->getUser();

        if (!$user instanceof User || $project->getOwner()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException(
                'You do not have access to this project.'
            );
        }
    }

}
