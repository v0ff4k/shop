<?php

declare(strict_types=1);

namespace App\Store\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
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
            ->add(
                'username',
                null,
                [
                    'label' => 'Your login to Store ',
                    'translation_domain' => 'FOSUserBundle',
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => 'StoreShopBundle\Entity\Customer',
            ]
        );
    }
}
