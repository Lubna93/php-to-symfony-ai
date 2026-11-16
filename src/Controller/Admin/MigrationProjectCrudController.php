<?php

namespace App\Controller\Admin;

use App\Entity\MigrationProject;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class MigrationProjectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MigrationProject::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('name'),
            TextField::new('sourceFramework', 'Source'),
            TextField::new('targetFramework', 'Target'),
            TextField::new('statusLabel', 'Status')
                ->setSortable(false),
            AssociationField::new('owner')->formatValue(
                static fn ($value, ?MigrationProject $entity) =>
                    $entity?->getOwner()?->getUsername() ?? '—'
            ),
            DateTimeField::new('createdAt'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', Action::DETAIL)
            ->disable(
                Action::NEW,
                Action::DELETE,
                Action::BATCH_DELETE
            );
    }
    
}
