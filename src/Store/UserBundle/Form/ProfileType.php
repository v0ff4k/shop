<?php

declare(strict_types=1);

namespace App\Store\UserBundle\Form;

use FOS\UserBundle\Util\LegacyFormHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // $builder = "";

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
                    'label' => 'Login ',
                    'translation_domain' => 'FOSUserBundle',
                ]
            )
//            ->add('email', LegacyFormHelper::getType('Symfony\Component\Form\Extension\Core\Type\EmailType'), array(
//                'label' => 'form.email',
//                'translation_domain' => 'FOSUserBundle'))
//            ->add('rules', null, array('label' => 'Rules ', 'translation_domain' => 'FOSUserBundle'))
        ;
    }

    public function getParent()
    {
        return 'FOS\UserBundle\Form\Type\ProfileFormType';
    }

    public function getName()
    {
        return $this->getBlockPrefix();
    }

    // For Symfony 2.x

    public function getBlockPrefix()
    {
        return 'app_user_edition';
    }
}
