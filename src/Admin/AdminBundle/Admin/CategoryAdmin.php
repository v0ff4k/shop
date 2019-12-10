<?php

namespace App\Admin\AdminBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class CategoryAdmin extends AbstractAdmin
{
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('id', null, array('label' => 'Category id '))
            ->add('title')
            ->add('image')
            ->add('added')
            ->add('editedDate');
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('id', 'text', array('label' => '#'))
            ->addIdentifier('title')
            ->addIdentifier('image')
            ->addIdentifier('added')
            ->addIdentifier('editedDate')
            
        //if use "->addIdentifier" dond use "->add" "_action" !!!
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
            ->add('title')
            ->add('image')
            ->add('added')
            ->add('editedDate');
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('id')
            ->add('title')
            ->add('image')
            ->add('added')
            ->add('editedDate');
    }
}
