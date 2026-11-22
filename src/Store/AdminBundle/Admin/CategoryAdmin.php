<?php

declare(strict_types=1);

namespace App\Store\AdminBundle\Admin;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\ValueObject\CategoryId;
use App\Infrastructure\Storage\FileUploadService;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\CollectionType; // (for list)
// use Symfony\Component\Form\Extension\Core\Type\CollectionType; // not full collection(for edit)
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Рефакторированный CategoryAdmin с современными практиками.
 *
 * Загрузка изображения категории обрабатывается в prePersist/preUpdate
 * через FileUploadService.
 */
final class CategoryAdmin extends AbstractAdmin
{
    // used in search()
    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'ASC',
        '_per_page' => 20,
        '_sort_by' => 'title',
    ];

    public function __construct(
        private readonly FileUploadService $fileUploadService,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
        parent::__construct();
    }

    /**
     * Обработка загрузки изображения.
     */
    public function prePersist(object $category): void
    {
        $this->handleImageUpload($category);
        $this->generateSlug($category);

        // MB not needed.
        // $this->preUpdate($category);
    }

    /**
     * @param Category|object $category
     */
    public function preUpdate(object $category): void
    {
        $this->handleImageUpload($category);

        $form = $this->getForm();

        // Заполнено поле "children"
        if ($form->has('children')) {
            $childrenData = $form->get('children')->getData();

            // Если Sonata Admin передает массив ID
            if (\is_array($childrenData) && \count($childrenData) > 0 && is_numeric($childrenData[0])) {
                $category->getChildren()->clear();

                foreach ($childrenData as $childId) {
                    if ($child = $this->categoryRepository->findById(CategoryId::fromInt($childId))) {
                        $category->addChild($child);
                    }
                }
            }
        }
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
            ->add('added', null, [
                'label' => 'Дата создания',
            ])
            ->add('parent', null, ['label' => 'Родительская категория(0 или 1)', 'required' => false])
            ->add('children', null, ['label' => 'Дочерние категории(0 или 1 или более)', 'required' => false])
            ->add('image', null, [
                'label' => 'Изображение',
            ])
        ;
    }

    // list table
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id', null, [
                'label' => '#',
                'route' => ['name' => 'show'],
            ])
            ->add('title', null, [
                'label' => 'Название',
                'route' => ['name' => 'edit'],
            ])
            ->add('slug', null, ['label' => 'slug'])
            ->add('image', null, [
                'label' => 'Изображение',
                'template' => 'admin/fields/category_image.html.twig',
            ])
            ->add('parent', null, ['label' => '"Родитель (0|1)"', 'row_align' => 'center'])
            ->add('children', null, ['label' => '"Дочерний (0-..)"', 'row_align' => 'center'])

            ->add('added', 'datetime', ['label' => 'Создана', 'format' => 'd/m/Y H:i'])
//            ->add('edited', 'datetime', ['label' => 'Изменено','format' => 'd.m.Y H:i',])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'label' => 'Действия',
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ])
        ;
    }

    // edit/creating new
    protected function configureFormFields(FormMapper $form): void
    {
        /** @var Category $subject */
        $subject = $this->getSubject();
        $imageUrl = $subject ? $subject->getImageSrc() : '';

        $isNew = empty($subject->getId());

        $form
            ->with('Основная информация', ['class' => 'col-md-8'])
            ->add('title', TextType::class, [
                'label' => 'Название',
                'attr' => ['maxlength' => 128],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'attr' => ['maxlength' => 32],
            ])

            ->add('parent', null, [
                'label' => 'Родительская категория (если есть)',
                'class' => Category::class,
                'required' => false,      // Allows the field to be submitted empty
                'choice_label' => 'title',
                'choice_attr' => function (Category $image) {
                    return [
                        'data-img' => $this->fileUploadService->getPublicThumbUrl(''.$image->getImage(), 'categories'),
                    ];
                },
                'attr' => [
                    'data-sonata-select2-allow-clear' => 'true', // Force Select2 to render "x", to clear the option
                    'class' => 'select2-with-images', // Custom class for targetted styling
                ],
            ]
            )
            // For EntityType Defined options are: "action", "allow_extra_fields", "allow_file_upload", "attr",
            //  "attr_translation_parameters", "auto_initialize", "block_name", "block_prefix", "by_reference",
            // "choice_attr", "choice_filter", "choice_label", "choice_lazy", "choice_loader", "choice_name",
            // "choice_translation_domain", "choice_translation_parameters", "choice_value", "choices", "class",
            // "compound", "constraints", "csrf_field_name", "csrf_message", "csrf_protection", "csrf_token_id",
            // "csrf_token_manager", "data", "data_class", "disabled", "duplicate_preferred_choices", "em", "empty_data",
            // "error_bubbling", "error_mapping", "expanded", "extra_fields_message", "form_attr", "getter", "group_by",
            // "help", "help_attr", "help_html", "help_translation_parameters", "horizontal_input_wrapper_class",
            // "horizontal_label_class", "horizontal_label_offset_class", "id_reader", "inherit_data", "invalid_message",
            // "invalid_message_parameters", "is_empty_callback", "label", "label_attr", "label_format", "label_html",
            // "label_render", "label_translation_parameters", "mapped", "method", "multiple", "placeholder", "placeholder_attr",
            // "post_max_size_message", "preferred_choices", "priority", "property_path", "query_builder", "required",
            // "row_attr", "separator", "separator_html", "setter", "sonata_admin", "sonata_field_description",
            // "sortable", "translation_domain", "trim", "upload_max_size_message", "validation_groups".
            ->add('children', EntityType::class, [
                'label' => 'Дочерние категории (если есть)',
                'class' => Category::class,
                'required' => false,      // Allows the field to be submitted empty
                'multiple' => true,
                'choice_label' => 'title',

                'by_reference' => false, // КРИТИЧЕСКИ ВАЖНО!

                'choice_attr' => function (Category $image) {
                    return [
                        'data-img' => $this->fileUploadService->getPublicThumbUrl(''.$image->getImage(), 'categories'),
                    ];
                },
                'attr' => [
                    'class' => 'select2-with-images', // Custom class for targetted styling
                ],
            ]
            )

            ->end()
            ->with('Изображение', ['class' => 'col-md-4'])
        ;
        if (!$isNew) {
            $form
                ->add('imageSrc', TextType::class, [
                    'label' => 'Путь до изображения(если есть)',
                    'required' => false,
                    'disabled' => true,
                    'empty_data' => 'нет изображения',
                    'help' => $imageUrl
                        ? 'Предпросмотр <br /><img src="'.$this->fileUploadService->getPublicThumbUrl($imageUrl, 'categories').
                        '" style="max-height: 80px; width: auto; display: inline-block;" alt="category preview">'
                        : '[No image]',
                    'help_html' => true, // use HTML tags

                    /*     // <label *> + <div *><img />
                'label_attr' => [ // <label *> + <div *><img />
                    'style' => 'color: gray; cursor: not-allowed;',
                    'title' => 'Если пустое, превью - не будет',],  */

                    // <label> <div *><img />
                    'help_attr' => [
                        'class' => 'inline-block text-center col-md-12',
                        'title' => 'Если пустое, превью - не будет', ],
                    // <label *>  <div><img />
                    'attr' => [
                        'style' => 'color: #7d4b4b; cursor: not-allowed;',
                        'title' => 'Если пустое, превью, внизу - не будет',
                    ],
                    // "action", "allow_extra_fields", "allow_file_upload", "attr", "attr_translation_parameters", "auto_initialize",
                    // "block_name", "block_prefix", "by_reference", "compound", "constraints",
                    // "csrf_field_name", "csrf_message", "csrf_protection", "csrf_token_id", "csrf_token_manager",
                    // "data", "data_class", "disabled", "empty_data",
                    // "error_bubbling", "error_mapping", "extra_fields_message", "form_attr", "getter", "help", "help_attr", "help_html",
                    // "help_translation_parameters", "horizontal_input_wrapper_class", "horizontal_label_class",
                    // "horizontal_label_offset_class", "inherit_data", "invalid_message", "invalid_message_parameters",
                    // "is_empty_callback", "label", "label_attr", "label_format", "label_html", "label_render",
                    // "label_translation_parameters", "mapped", "method", "post_max_size_message", "priority", "property_path",
                    // "required", "row_attr", "sanitize_html", "sanitizer", "setter", "sonata_admin", "sonata_field_description",
                    // "translation_domain", "trim", "upload_max_size_message", "validation_groups"
                ])
            ;
        }
        $form
            ->add('image', FileType::class, [
                'label' => ($isNew ? 'Выбрать изображение' : 'Чтобы изменить, выберете новое'),
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/*'],
            ])/*
                ->add('image', TextType::class, [
                    'label' => 'Путь изображения',
                    'required' => false,
                    'attr' => ['maxlength' => 255],
                ])*/
//            ->end()
//            ->with('Метаданные', ['class' => 'col-md-8'])
            ->add('added', DateTimeType::class, [
                'label' => 'Дата создания(авто)',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['maxlength' => 32],
            ])
            ->end();
    }

    // custom template per EDIT configureFormFields()
    protected function configure(): void
    {
        // custom templete extend for editing:  configureFormFields()
        $this->setTemplate('edit', 'admin/edit_custom.html.twig');
    }

    // show is clicked.
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->with('Основная информация', ['class' => 'col-md-6'])
            ->add('id', null, ['label' => 'ID'])
            ->add('title', null, ['label' => 'Название'])
            ->add('slug', null, ['label' => 'Slug'])
            ->add('parent', null, ['label' => 'Родительская категория (если есть)'])
            ->add('children', null, [ // CollectionType::class
                'label' => 'Дочерние категории (если есть)',
                'class' => Category::class,
                'multiple' => true,
                'choice_label' => 'title',
            ]
            )
            ->end()
            ->with('Дата', ['class' => 'col-md-6'])
            ->add('added', 'datetime', [
                'label' => 'Создано',
                'format' => 'd.m.Y H:i:s',
            ])
            ->add('edited', 'datetime', [
                'label' => 'Изменено',
                'format' => 'd.m.Y H:i:s',
            ])
            ->end()
            ->with('Изображение, как есть', ['class' => 'col-md-12'])
            ->add('image', null, [
                'label' => 'Изображение категории',
                'type' => 'text',
                'virtual_field' => false,
                'format' => '%s',
                'template' => 'admin/fields/category_image_show.html.twig',
            ])
            ->end()
        ;
    }

    private function handleImageUpload(Category $category): void
    {
        $form = $this->getForm();
        if ($form === null || !$form->has('image')) {
            return;
        }

        $file = $form->get('image')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $category->setImage($this->fileUploadService->uploadCategoryImage($file));
    }

    private function generateSlug(Category $category): void
    {
        if (empty($category->getSlug()) && !empty($category->getTitle())) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-',
                iconv('utf-8', 'ASCII//TRANSLIT', $category->getTitle())
            ), '-'));
            $category->setSlug($slug);
        }
    }
}
