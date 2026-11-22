<?php

declare(strict_types=1);

namespace App\Store\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'fullname',
                null,
                [
                    'label' => 'Displayed, Full name ',
                    'translation_domain' => 'FOSUserBundle',
                ]
            )
            ->add(
                'address',
                null,
                [
                    'label' => 'Shipping address ',
                    'translation_domain' => 'FOSUserBundle',
                ]
            )
//            ->add('username', null, array(
//                'label' => 'Your login to Store: ',
//                'translation_domain' => 'FOSUserBundle'))
        ;
    }

    public function getParent()
    {
        return 'FOS\UserBundle\Form\Type\RegistrationFormType';
    }

    public function getName()
    {
        return $this->getBlockPrefix();
    }

    // For Symfony 2.x

    public function getBlockPrefix()
    {
        return 'app_user_registration';
    }
}
