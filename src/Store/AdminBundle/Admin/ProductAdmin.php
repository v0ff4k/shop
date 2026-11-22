<?php

declare(strict_types=1);

namespace App\Store\AdminBundle\Admin;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use App\Infrastructure\Storage\FileUploadService;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Рефакторированный ProductAdmin с использованием современных практик
 * - Строгая типизация
 * - Современные Symfony Form типы
 * - Улучшенный UX (фильтры, сортировка, пагинация)
 * - Поддержка DDD (работа с Domain Entity).
 */
final class ProductAdmin extends AbstractAdmin
{
    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_per_page' => 20,
        '_sort_by' => 'added',
    ];
    private HtmlSanitizerInterface $rtfSanitizer;

    private FileUploadService $fileUploadService;

    // Inject the specific RTF sanitizer config via constructor
    public function __construct(
        HtmlSanitizerInterface $rtfSanitizer,
        FileUploadService $fileUploadService)
    {
        parent::__construct();
        $this->rtfSanitizer = $rtfSanitizer;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Overrides how the object is converted to a string inside Admin context.
     * Will be display in breadcrumb on View/Edit.
     *
     * @param object|Product $object
     */
    public function toString(object $object): string
    {
        // Check if it's your expected entity instance
        /** @var Product $object */
        if (method_exists($object, 'getid') && $object->getid()) {
            return '#'.$object->getid().' '.$object->getTitle().' ('.$object->getSlug().')';
        }

        return parent::toString($object);
    }

    /**
     * Предзаполняем slug+Description при создании.
     */
    public function prePersist(object $product): void
    {
        $this->generateSlug($product);
        $this->sanitizeDescription($product);
        $this->handleNewImages($product);
    }

    /**
     * Обновляем slug при редактировании.
     */
    public function preUpdate(object $product): void
    {
        $this->generateSlug($product);
        $this->sanitizeDescription($product);
        $this->handleNewImages($product);
    }

    // Filter block
    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('id', null, [
                'label' => 'ID',
            ])
            ->add('title', null, [
                'label' => 'Название',
                'show_filter' => true,
            ])
            ->add('category', null, [
                'label' => 'Категория',
                'field_type' => EntityType::class,
                'field_options' => [
                    'class' => Category::class,
                    'choice_label' => 'title',
                    'required' => false,
                    'placeholder' => 'Все категории',
                ],
            ])
            ->add('price', null, [
                'label' => 'Цена',
            ])
            ->add('available', null, [
                'label' => 'В наличии',
            ])
            ->add('added', null, ['label' => 'Дата добавления', 'format' => 'd.m.Y H:i'])
            ->add('lastViewed', null, ['label' => 'Просмотрено', 'format' => 'd.m.Y H:i'])
            ->add('edited', null, ['label' => 'Изменено', 'format' => 'd.m.Y H:i'])
        ;
    }

    // list table
    protected function configureListFields(ListMapper $list): void
    {
        $actions = [
            'label' => 'Действия',
            'template' => 'admin/actions/list__action.html.twig',
            'actions' => [
                'show' => ['template' => 'admin/actions/list__action_show.html.twig'],
                'edit' => ['template' => 'admin/actions/list__action_edit.html.twig'],
                'delete' => ['template' => 'admin/actions/list__action_delete.html.twig'],
            ],
        ];

        $list
            ->add('id', null, ['label' => '#', 'route' => ['name' => 'show']])
            ->add('title', null, ['label' => 'Название', 'route' => ['name' => 'edit']])
            ->add('slug', null, ['label' => 'Часть ссылки'])
            ->add('price', null, ['label' => 'Цена'])
            ->add('category', null, ['label' => 'Категория'])
            ->add('images', null, ['label' => 'Изображение', 'template' => 'admin/fields/product_images.html.twig'])// images !
            ->add('available', null, ['label' => 'Кол-во'])
            ->add('viewed', null, ['label' => 'Просмотры'])

            ->add('added', 'datetime', ['label' => 'Создан', 'format' => 'd.m.Y H:i'])
//            ->add('lastViewed', 'datetime', ['label' => 'Просмотрено', 'format' => 'd.m.Y H:i'])
//            ->add('edited', 'datetime', ['label' => 'Изменено', 'format' => 'd.m.Y H:i'])
            ->add(ListMapper::NAME_ACTIONS, ListMapper::TYPE_ACTIONS, $actions)
        ;
    }

    // edit/creating new
    protected function configureFormFields(FormMapper $form): void
    {
        $form

            ->with('Основная информация', ['class' => 'col-md-8'])
            ->add('title', TextType::class, [
                'label' => 'Название',
                'attr' => ['maxlength' => 255],
            ])
            ->add('description', SimpleFormatterType::class, [
                'format' => 'richhtml',
                'label' => 'Описание (можно использовать простой HTML код)',
                'required' => false,
                'attr' => ['class' => 'html-editor', 'rows' => 12],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug(если будет пустым, заполнится авто)',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->end()

            ->with('Цена и наличие', ['class' => 'col-md-4'])
            ->add('price', null, [ // MoneyType::class
                'label' => 'Цена',
                //                    'currency' => 'RUB',
                'required' => true,
            ])
            ->add('available', IntegerType::class, [
                'label' => 'Количество',
                'required' => true,
            ])
            ->add('category', EntityType::class, [
                'label' => 'Категория',
                'class' => Category::class,
                'choice_label' => 'title',
                'choice_attr' => function (Category $category) {
                    return [
                        'data-img' => $this->fileUploadService->getPublicThumbUrl(''.$category->getImage(), 'categories'),
                    ];
                },
                'required' => true,
                'attr' => [
                    'class' => 'select2-with-images', // Custom class for targetted styling if needed
                ],
            ])
            ->add('images', EntityType::class, [ // ModelType::class
                'label' => 'Выберите изображения',
                'class' => ProductImage::class,
                'multiple' => true,
                'by_reference' => false,
                'choice_label' => static fn (ProductImage $image): string => $image->getAlt() ?: $image->getPath(),
                'choice_attr' => function (ProductImage $image) {
                    return [
                        'data-img' => $this->fileUploadService->getPublicThumbUrl(''.$image->getPath(), 'products'),
                    ];
                },
                'attr' => [
                    'class' => 'select2-with-images', // Custom class for targetted styling if needed
                ],
                'required' => true,
            ])
            ->add('image_file', FileType::class, [
                'label' => 'Загрузить новые изображения',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'attr' => ['accept' => 'image/*'],
            ])
            ->add('added', DateTimeType::class, [
                'label' => 'Дата добавления(по умолчанию, текущая дата)',
                'input_format' => 'd.m.Y H:i',
                'widget' => 'single_text',
                'required' => false,
            ])

            ->end()

            ->with('Статистика', ['class' => 'col-md-6'])
            ->add('viewed', IntegerType::class, [
                'label' => 'Просмотры',
                'disabled' => true,
                'required' => false,
            ])
            ->add('lastViewed', DateTimeType::class, [
                'label' => 'Последний просмотр',
                'widget' => 'single_text',
                'disabled' => true,
                'required' => false,
            ])
            ->end()

            ->with('Дата изменения', ['class' => 'col-md-6'])
            ->add('edited', DateTimeType::class, [
                'label' => 'Дата изменения',
                'widget' => 'single_text',
                'disabled' => true,
                'required' => false,
            ])
            ->end()

        ;
    }

    protected function configure(): void
    {
        // custom templete extend for editing:  configureFormFields()
        $this->setTemplate('edit', 'admin/edit_custom.html.twig');
    }

    // show is clicked.
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->with('Основная информация')
            ->add('id', null, ['label' => 'ID'])
            ->add('title', null, ['label' => 'Название'])
            ->add('slug', null, ['label' => 'Slug'])
            ->add('description', 'html', [
                'label' => 'Описание',
                'truncate' => 0, // other val, will auto-trim html tags, but <30 symbols !
                'template' => 'admin/fields/show_html.html.twig',
                'attr' => ['class' => 'border p-3 rounded btn-lg inline-block'],
            ]
            )

                // EntityType::class
            ->add('images', null, [
                'label' => 'Изображения',
                'template' => 'admin/fields/show_product_images.html.twig', // show images in array.
                'attr' => ['class' => 'border rounded btn-sm inline-block'],
            ]
            )
            ->end()
            ->with('Цена и наличие')
            ->add('price', null, ['label' => 'Цена'])
            ->add('available', null, ['label' => 'Количество'])
            ->end()
            ->with('Категория')
            ->add('category.title', null, ['label' => 'Категория'])
            ->end()
            ->with('Статистика')
            ->add('viewed', null, ['label' => 'Просмотры'])
            ->add('added', 'datetime', ['label' => 'Добавлен', 'format' => 'd.m.Y H:i:s'])
            ->add('edited', 'datetime', ['label' => 'Обновлено', 'format' => 'd.m.Y H:i:s'])
            ->add('lastViewed', 'datetime', ['label' => 'Последний просмотр', 'format' => 'd.m.Y H:i:s'])
            ->end();
    }

    private function handleNewImages(Product $product): void
    {
        $form = $this->getForm();
        if ($form === null || !$form->has('image_file')) {
            return;
        }

        $files = $form->get('image_file')->getData();
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (!\is_array($files)) {
            return;
        }

        $existingPaths = array_map(
            static fn (ProductImage $image): string => $image->getPath(),
            $product->getImages()
        );
        $position = \count($existingPaths);

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $this->fileUploadService->uploadProductImage($file);
            if (\in_array($path, $existingPaths, true)) {
                continue;
            }

            $image = new ProductImage($path);
            $image->setPosition($position++);
            $product->addImage($image);
            $existingPaths[] = $path;
        }
    }

    private function generateSlug(Product $product): void
    {
        if (empty($product->getSlug()) && !empty($product->getTitle())) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-',
                iconv('utf-8', 'ASCII//TRANSLIT', $product->getTitle())
            ), '-'));

            $product->setSlug($slug);
        }
    }

    private function sanitizeDescription(Product $product): void
    {
        $dirtyHtml = $product->getDescription();

        if ($dirtyHtml !== null) {
            // Cleans MS Word markup, removes images, and secures HTML
            $cleanHtml = $this->rtfSanitizer->sanitize($dirtyHtml);
            $product->setDescription($cleanHtml);
        }
    }
}
