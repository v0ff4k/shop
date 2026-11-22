<?php

declare(strict_types=1);

namespace App\Store\AdminBundle\Admin;

use App\Domain\Shop\Entity\ProductImage;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Упрощенный ProductimageAdmin без загрузки связанных сущностей
 * /admin/domain/shop/productimage/*
 * Избегает циклических ссылок ProductImage → Product → Category.
 *
 * @deprecated
 */
final class SimpleProductimageAdmin extends AbstractAdmin
{
    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_per_page' => 20,
        '_sort_by' => 'id',
    ];

    /**
     * Overrides how the object is converted to a string inside Admin context.
     * Will be display in breadcrumb on View/Edit.
     *
     * @param object|ProductImage $object
     */
    public function toString(object $object): string
    {
        // Check if it's your expected entity instance
        if (method_exists($object, 'getid') && $object->getid()) {
            return '#'.$object->getid().' alt: '.$object->getAlt().'';
        }

        return parent::toString($object);
    }

    // Filter block
    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('id', null, ['label' => 'ID'])
            ->add('path', null, ['label' => 'Путь'])
            ->add('product', null, ['label' => 'Товар'])
            ->add('position', null, ['label' => 'Позиция'])
            ->add('alt', null, ['label' => 'Описание', 'show_filter' => true])
            ->add('uploadedAt', null, ['label' => 'Дата добавления'])
        ;
    }

    // list table
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id', null, ['label' => '#', 'route' => ['name' => 'show']])
            ->add('product', null, ['label' => 'товар'])
            ->add('path', null, ['label' => 'Изображение', 'template' => 'admin/fields/product_image.html.twig'])
            ->add('alt', null, ['label' => 'Описание'])
            ->add('position', null, ['label' => 'Позиция'])
            ->add('uploadedAt', 'datetime', ['label' => 'Загружено', 'format' => 'd.m.Y H:i'])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'label' => 'Действия',
                'actions' => ['show' => [], 'edit' => [], 'delete' => []],
            ])
        ;
    }

    // creating new / editin
    protected function configureFormFields(FormMapper $form): void
    {
        $form

            ->with('Изображения', ['class' => 'col-md-4'])
//                ->add('path', TextType::class, ['label' => 'Путь к файлу', 'required' => true])
            ->add('path', FileType::class, [
                'label' => 'Не выбирайте, чтобы не менять',
                'required' => true,
                'mapped' => false,
                'attr' => ['accept' => 'image/*'],
            ])
            ->end()
            ->with('Данные', ['class' => 'col-md-8'])
            ->add('alt', TextType::class, ['label' => 'Описание (alt)', 'required' => false])
//                ->add('path', TextType::class, ['label' => 'Путь к файлу', 'required' => true])// display on EDIT
            ->add('position', IntegerType::class, ['label' => 'Позиция', 'required' => false])
            ->add('product', null, ['label' => 'Товар', 'required' => false])
            ->end();
    }

    // show is clicked.
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id', null, ['label' => 'ID'])
            ->add('path', null, ['label' => 'Путь к файлу'])
            ->add('product', null, ['label' => 'Товар', 'required' => false])
            ->add('alt', null, ['label' => 'Описание'])
            ->add('position', null, ['label' => 'Позиция'])
            ->add('uploadedAt', 'datetime', ['label' => 'Загружено', 'format' => 'd.m.Y H:i:s']);
    }
}
