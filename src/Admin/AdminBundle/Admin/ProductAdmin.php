<?php

namespace App\Admin\AdminBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class ProductAdmin extends AbstractAdmin
{

    public $last_position = 0;
    private $positionService;
    
    // make custom sort page
    protected $datagridValues = array(
        '_page' => 1, //1st page will display 1st
        '_sort_order' => 'DESC', //newest 1st
        '_per_page' => 10, //limit
        '_sort_by' => 'added', //by post date
    );

    //    public function setPositionService(\Pix\SortableBehaviorBundle\Services\PositionHandler $positionHandler)
    //    {
    //        $this->positionService = $positionHandler;
    //    }
    //
    //    protected function configureRoutes(RouteCollection $collection)
    //    {
    //        $collection->add('move', $this->getRouterIdParameter() . '/move/{position}');
    //    }

    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('id', null, array('label' => 'Product id '))
            ->add('title')
            ->add('price')
            ->add('description')
            ->add('category')
            ->add('available')
            ->add('added')
            ->add('viewed')
            ->add('lastViewed');
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('id', 'text', array('label' => '#'))
            ->addIdentifier('title')
            ->addIdentifier('price', 'decimal')
            ->addIdentifier('description')
            ->addIdentifier('category.title', null, array('label' => 'Category'))
            ->addIdentifier('available')
            ->addIdentifier('added')
            ->addIdentifier('viewed')
            ->addIdentifier('lastViewed')
            
        //                ->add('_action', null, array(
        //                    'actions' => array(
        //                        'move' => array(
        //                            'template' => 'AppBundle:Admin:_sort.html.twig'
        //                        ),
        //                    ),
        //                ))
            
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
            ->add('price')
            ->add('description')
            ->add('category')
            ->add('available')
            ->add('added')
            ->add('viewed')
            ->add('lastViewed');
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('id')
            ->add('title')
            ->add('price')
            ->add('description')
            ->add('category')
            ->add('available')
            ->add('added')
            ->add('viewed')
            ->add('lastViewed');
    }
}
