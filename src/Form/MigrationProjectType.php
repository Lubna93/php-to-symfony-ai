<?php

namespace App\Form;

use App\Entity\MigrationProject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MigrationProjectType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Project Name',
                'attr' => [
                    'placeholder' => 'My Legacy Application',
                ],
            ])
            ->add('sourceFramework', ChoiceType::class, [
                'label' => 'Source Framework',
                'placeholder' => 'Select source framework',
                'choices' => [
                    'Plain PHP' => 'Plain PHP',
                    'Zend Framework' => 'Zend Framework',
                    'Symfony (Legacy)' => 'Symfony Legacy',
                    'Laravel' => 'Laravel',
                ],
            ])
            ->add('targetFramework', ChoiceType::class, [
                'label' => 'Target Framework',
                'placeholder' => 'Select target framework',
                'choices' => [
                    'Symfony 6.4 LTS' => 'Symfony 6.4',
                    'Symfony 7.4 LTS' => 'Symfony 7.4',
                    'Symfony 8.0' => 'Symfony 8.0',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MigrationProject::class,
        ]);
    }
}
