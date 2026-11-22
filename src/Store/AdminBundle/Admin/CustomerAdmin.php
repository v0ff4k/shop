<?php

declare(strict_types=1);

namespace App\Store\AdminBundle\Admin;

use App\Domain\Order\Entity\Order;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Secure;
use App\Infrastructure\Storage\FileUploadService;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType; // SymfonyChoiceType
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Рефакторированный CustomerAdmin с современными практиками
 * TODO Failed to create object: App\Domain\User\Entity\User  on create new.
 */
final class CustomerAdmin extends AbstractAdmin
{
    // used in search()
    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'ASC',
        '_per_page' => 20,
        '_sort_by' => 'email',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Security $security,
        private readonly FileUploadService $fileUploadService,
    ) {
        parent::__construct();
    }

    /**
     * Overrides how the object is converted to a string inside Admin context.
     * Will be display in breadcrumb on View/Edit.
     *
     * @param object|User $object
     */
    public function toString(object $object): string
    {
        // Check if it's your expected entity instance
        if (method_exists($object, 'getid') && $object->getid()) {
            return '#'.$object->getid().' '.$object->getFullName().' ('.$object->getUsername().')';
        }

        return parent::toString($object);
    }

    /**
     * Хешируем пароль при сохранении.
     */
    public function prePersist(object $user): void
    {
        $this->handleAvatarUpload($user);
        $this->hashPassword($user);
    }

    public function preUpdate(object $user): void
    {
        $this->handleAvatarUpload($user);
        $this->hashPassword($user);
    }

    // Filter block
    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('id', null, ['label' => 'ID'])
            ->add('username', null, ['label' => 'Username', 'show_filter' => true])
            ->add('email', null, ['label' => 'Email', 'show_filter' => true])
            ->add('enabled', null, ['label' => 'Активен', 'field_type' => CheckboxType::class])
            ->add('roles', CallbackFilter::class, [
                'label' => 'Роль',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => User::CHOICES,
                    'multiple' => true,
                    'required' => false,
                ],
                'callback' => static function ($queryBuilder, string $alias, string $field, FilterData $data): bool {
                    // Максимально подходящая, по Доктрина + Постгре + JSON, для выборки роли(1+) пользователя.
                    if (!$data->hasValue()) {
                        return false;
                    }

                    $selectedValue = $data->getValue();

                    // Deep-unwrap the value for Sonata 4
                    if (\is_array($selectedValue)) {
                        $selectedValue = isset($selectedValue['value']) ? $selectedValue['value'] : reset($selectedValue);
                    }

                    if ($selectedValue === null || $selectedValue === '') {
                        return false;
                    }

                    // CONCAT is perfectly legal in Doctrine DQL.
                    // By passing an empty string '', PostgreSQL implicitly translates the JSON array context
                    // into a plain TEXT stream during the concatenation process to execute the instruction.
                    $queryBuilder
                        ->andWhere(\sprintf("CONCAT(%s.roles, '') LIKE :roleParameter", $alias))
                        ->setParameter('roleParameter', '%"'.$selectedValue.'"%');

                    return true;
                },
                // prevent Sonata getting confused how to render the active filter val.
                'operator_type' => ChoiceType::class,
                'operator_options' => [
                    'choices' => [
                        'equal' => 'equal',
                    ],
                ],
                'template' => 'admin/filter/json_role_filter.html.twig',
            ])
        ;
    }

    // list table
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id', null, ['label' => '#', 'route' => ['name' => 'show']])
            ->add('email', null, ['label' => 'Email', 'route' => ['name' => 'edit']])
            ->add('enabled', 'boolean', ['label' => 'Активен'])
            ->add('roles', 'choice', ['label' => 'Роли', 'choices' => array_flip(User::CHOICES), 'multiple' => true])
            ->add('registeredAt', 'datetime', ['label' => 'Зарегестрирован', 'format' => 'd.m.Y H:i'])
            // ->add('lastLoginAt', 'datetime', [ 'label' => 'Последний вход', 'format' => 'd.m.Y H:i', ])
            // ->add('updatedAt', 'datetime', [ 'label' => 'Обновлен', 'format' => 'd.m.Y H:i', ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'label' => 'Действия',
                'actions' => ['show' => [], 'edit' => [], 'delete' => []],
            ]);
    }

    // creating new / editin
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('Основная информация', ['class' => 'col-md-6'])
            ->add('username', null, ['label' => 'Username', 'required' => true])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => true])
            ->add('fullName', null, ['label' => 'Полное имя', 'required' => true])
            ->add('address', null, ['label' => 'Адрес', 'required' => true])
            ->add('avatar_file', FileType::class, [
                'label' => 'Аватар (выберите файл, чтобы загрузить/заменить)',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*'],
            ])
        ;

        // Check if the currently logged-in user with specific roles
        if ($this->security->isGranted(User::ROLE_STAFF) || $this->security->isGranted(User::ROLE_ADMIN)) {
            $form->add('additionalInfo', TextareaType::class, [
                'label' => 'Доп инфо',
                'required' => false,
                'attr' => ['title' => 'Будет видно только тех-сотрудникам и Админу'], // todo
            ])
            ; // only for admin/stuff
        }
        $form
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Пароль (оставьте пустым, если не меняете)',
                'required' => false,
                'always_empty' => false,
            ])
            ->end()
            ->with('Статус и роли', ['class' => 'col-md-6'])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Активен',
                'required' => false,
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Роли',
                'choices' => User::CHOICES,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
            ])
            ->end();
    }

    // show is clicked.
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->with('Основная информация', ['class' => 'col-md-6'])
            ->add('id', null, ['label' => 'ID'])
            ->add('email', null, ['label' => 'Email'])
            ->add('username', null, ['label' => 'Username'])
            ->add('fullName', null, ['label' => 'Имя'])
            ->add('address', null, ['label' => 'Адрес'])
            ->add('avatar', null, ['label' => 'Аватар (путь)']);
        // Check if the currently logged-in user with specific roles
        if ($this->security->isGranted(User::ROLE_STAFF) || $this->security->isGranted(User::ROLE_ADMIN)) {
            $show->add('additionalInfo', 'html', [
                'label' => 'Доп инфо*',
                'truncate' => 0, // other val, will auto-trim html tags, but <30 symbols !
                'template' => 'admin/fields/show_html.html.twig',
                'attr' => ['title' => 'Поле и значение отображается ТОЛЬКО для сотрудников и админов', 'class' => 'border p-1 rounded btn-sm inline-block']]
            ); // only for admin/stuff
        }
        // ->add('additionalInfo', null, ['label' => 'Доп инфо'])//only for admin
        $show->add('enabled', 'boolean', ['label' => 'Активен'])
            ->end()
            ->with('Роли', ['class' => 'col-md-6'])
            ->add('roles', 'choice', [
                'label' => 'Роли',
                'choices' => array_flip(User::CHOICES),
                'multiple' => true,
                'delimiter' => ', ',
            ])
            ->end()
            ->with('Метаданные', ['class' => 'col-md-12'])
            ->add('orders', null, [
                'label' => 'Заказы',
                'class' => Order::class,
                'multiple' => true,
                'choice_label' => 'getHumanOrder', // TODO new line per order !!!!
            ])
            ->add('lastLoginAt', 'datetime', [
                'label' => 'Последний вход',
                'format' => 'd.m.Y H:i:s',
            ])
            ->add('registeredAt', 'datetime', [
                'label' => 'Зарегистрирован',
                'format' => 'd.m.Y H:i:s',
            ])
            ->add('updatedAt', 'datetime', [
                'label' => 'Изменён',
                'format' => 'd.m.Y H:i:s',
            ])
            ->end()
        ;
    }

    private function handleAvatarUpload(User $user): void
    {
        $form = $this->getForm();
        if ($form === null || !$form->has('avatar_file')) {
            return;
        }

        $file = $form->get('avatar_file')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $user->setAvatar($this->fileUploadService->uploadUserImage($file, $user->getId()));
    }

    private function hashPassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();

        if (!empty($plainPassword)) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }
    }

    /**
     * Rules per field and auth user role.
     *
     * @todo actualize!
     */
    private function isAccessableByField(string $field, array $roles): bool
    {
        return !(!Secure::isFieldAndRole($field, $roles))

        ;
    }
}
