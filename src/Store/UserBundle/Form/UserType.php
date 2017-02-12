<?php

namespace Store\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('fullname', null, array(
                'label' => 'Displayed, Full name ',
                'translation_domain' => 'FOSUserBundle'))
            ->add('address', null, array(
                'label' => 'Shipping address ',
                'translation_domain' => 'FOSUserBundle'))
            ->add('username', null, array(
                'label' => 'Your login to Store ',
                'translation_domain' => 'FOSUserBundle'))
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'StoreShopBundle\Entity\Customer'
        ));
    }
}
