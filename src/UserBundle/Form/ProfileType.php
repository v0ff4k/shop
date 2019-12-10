<?php

namespace UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use FOS\UserBundle\Util\LegacyFormHelper;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProfileType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //$builder = "";

        $builder
            ->add(
                'fullname', null, array(
                'label' => 'Displayed, Full name ',
                'translation_domain' => 'FOSUserBundle')
            )
            ->add(
                'address', null, array(
                'label' => 'Shipping address ',
                'translation_domain' => 'FOSUserBundle')
            )
            ->add(
                'username', null, array(
                'label' => 'Login ',
                'translation_domain' => 'FOSUserBundle')
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

    public function getBlockPrefix()
    {

        return 'app_user_edition';
    }

    // For Symfony 2.x
    public function getName()
    {
        return $this->getBlockPrefix();
    }
}
