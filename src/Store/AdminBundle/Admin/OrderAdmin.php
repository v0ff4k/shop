<?php

namespace Store\AdminBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class OrderAdmin extends AbstractAdmin
{
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('id')
            ->add('shipping')
            ->add('quantity')
            ->add('sold')
            ->add('created')
            ->add('edited')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('id')
            ->addIdentifier('shipping')
            ->addIdentifier('quantity')
            ->addIdentifier('sold')
            ->addIdentifier('created')
            ->addIdentifier('edited')
//            ->add('_action', null, array(
//                'actions' => array(
//                    'show' => array(),
//                    'edit' => array(),
//                    'delete' => array(),
//                )
//            ))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('shipping')
            ->add('quantity')
            ->add('sold', null, array('label' => 'Product is sold ? (if uncheck it, add prod num to store manually !!!)'))
            ->add('created')
            ->add('edited')
        ;
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('id')
            ->add('shipping')
            ->add('quantity')
            ->add('sold')
            ->add('created')
            ->add('edited')
        ;
    }
}
