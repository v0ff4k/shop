<?php

declare(strict_types=1);

namespace App\Store\ShopBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrdersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('shipping')
            ->add('quantity')
            ->add('sold', null, ['label' => 'Buy this product (this can\'t be undo!) '])
            // ->add('created')
            // ->add('edited')
            // ->add('product')
            // ->add('customer')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => 'App\Store\ShopBundle\Entity\Orders',
            ]
        );
    }

    public function getBlockPrefix()
    {
        return 'store_shopbundle_order';
    }
}
