<?php

declare(strict_types=1);

namespace App\Store\AdminBundle\Admin;

use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use App\Infrastructure\Storage\FileUploadService;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Админ-класс изображений товара.
 *
 * Позволяет создать изображение с загрузкой файла, изменить alt/позицию,
 * привязать изображение к другому товару или заменить файл.
 */
final class ProductimageAdmin extends AbstractAdmin
{
    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_per_page' => 20,
        '_sort_by' => 'id',
    ];

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {
        parent::__construct();
    }

    public function toString(object $object): string
    {
        if ($object instanceof ProductImage && $object->getId() !== null) {
            $title = $object->getAlt() ?: $object->getPath();
            $productTitle = $object->getProduct()?->getTitle() ?? 'без товара';

            return \sprintf('#%d %s (%s)', $object->getId(), $title, $productTitle);
        }

        return parent::toString($object);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('id', null, [
                'label' => 'ID',
            ])
            ->add('product', null, [
                'label' => 'Товар',
                'field_type' => EntityType::class,
                'field_options' => [
                    'class' => Product::class,
                    'choice_label' => 'title',
                    'required' => false,
                    'placeholder' => 'Все товары',
                ],
            ])
            ->add('path', null, [
                'label' => 'Путь к файлу',
            ])
            ->add('alt', null, [
                'label' => 'Описание',
                'show_filter' => true,
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id', null, ['label' => '#', 'route' => ['name' => 'show']])
            ->add('path', null, ['label' => 'Изображение', 'template' => 'admin/fields/product_image.html.twig'])
            ->add('alt', null, ['label' => 'Описание'])
            ->add('product', null, ['label' => 'Товар', 'row_align' => 'center'])
            ->add('position', null, ['label' => 'Позиция'])
            ->add('uploadedAt', 'datetime', ['label' => 'Загружено', 'format' => 'd.m.Y H:i'])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'label' => 'Действия',
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $subject = $this->getSubject();
        $isNew = !$subject instanceof ProductImage || $subject->getId() === null;
        $imageUrl = $subject instanceof ProductImage ? $subject->getPath() : '';

        $form
            ->with('Основная информация', ['class' => 'col-md-8'])
            ->add('product', EntityType::class, [
                'label' => 'Товар (для которого будет изображение)',
                'class' => Product::class,
                'choice_label' => 'title',
                'required' => true,
            ])
            ->add('alt', TextType::class, [
                'label' => 'Описание (alt text)',
                'required' => false,
            ])
            ->add('file', FileType::class, [
                'label' => $isNew
                    ? 'Файл изображения'
                    : 'Загрузить новый файл (необязательно, чтобы заменить)',
                'required' => $isNew,
                'mapped' => false,
                'attr' => ['accept' => 'image/*'],
            ])
            ->end()

            ->with('Настройки', ['class' => 'col-md-4'])
            ->add('position', IntegerType::class, [
                'label' => 'Позиция (приоритет в продукте)',
                'required' => false,
            ])
            ->end();

        if (!$isNew) {
            $form
                ->with('Текущий файл', ['class' => 'col-md-12'])
                ->add('path', TextType::class, [
                    'label' => 'Путь к файлу',
                    'required' => false,
                    'disabled' => true,
                    'help' => $imageUrl !== ''
                        ? 'Предпросмотр <br /><img src="'
                            .$this->fileUploadService->getPublicThumbUrl($imageUrl, 'products')
                            .'" style="max-height: 120px; width: auto; display: inline-block;" alt="image preview">'
                        : '[No image]',
                    'help_html' => true,
                    'help_attr' => [
                        'class' => 'inline-block text-center col-md-12',
                    ],
                ])
                ->end();
        }
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->with('Основная информация')
            ->add('id', null, ['label' => 'ID'])
            ->add('product.title', null, ['label' => 'Товар'])
            ->add('path', null, ['label' => 'Путь к файлу'])
            ->add('alt', null, ['label' => 'Описание'])
            ->end()
            ->with('Настройки')
            ->add('position', null, ['label' => 'Позиция'])
            ->end()
            ->with('Метаданные')
            ->add('uploadedAt', 'datetime', [
                'label' => 'Загружено',
                'format' => 'd.m.Y H:i:s',
            ])
            ->end();
    }

    protected function prePersist(object $object): void
    {
        if (!$object instanceof ProductImage) {
            return;
        }

        $this->applyUploadedFile($object);

        if ($object->getPath() === '') {
            throw new \InvalidArgumentException('Product image file is required');
        }
    }

    protected function preUpdate(object $object): void
    {
        if ($object instanceof ProductImage) {
            $this->applyUploadedFile($object);
        }
    }

    private function applyUploadedFile(ProductImage $image): void
    {
        $form = $this->getForm();
        if ($form === null || !$form->has('file')) {
            return;
        }

        $file = $form->get('file')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $image->setPath($this->fileUploadService->uploadProductImage($file));
    }
}
