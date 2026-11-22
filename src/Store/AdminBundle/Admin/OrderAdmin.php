<?php

declare(strict_types=1);

namespace App\Store\AdminBundle\Admin;

use App\Domain\Order\Entity\Order;
use App\Domain\User\Entity\User;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\NumberFilter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * OrderAdmin - соответствует сущности App\Domain\Order\Entity\Order
 * протестить !
 */
final class OrderAdmin extends AbstractAdmin
{
    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_per_page' => 20,
        '_sort_by' => 'createdAt',
    ];

    /**
     * Generate Form Fields default array. as DRY style.
     *
     * @return array
     */
    private static function dTimeFF(string $label = '')
    {
        return ['label' => $label, 'widget' => 'single_text', 'required' => false, 'input_format' => 'd.m.Y H:i:s'];
    }

    /**
     * Overrides how the object is converted to a string inside Admin context.
     * Will be display in breadcrumb on View/Edit.
     *
     * @param object|Order $object
     */
    public function toString(object $object): string
    {
        // Check if it's your expected entity instance
        if (method_exists($object, 'getid') && $object->getid()) {
            return '#'.$object->getid().' : '.$object->getHumanOrder().'';
        }

        return parent::toString($object);
    }

    // Filter block
    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('id', null, ['label' => 'ID'])
            ->add('user', null, [
                'label' => 'Пользователь(создал)',
                'field_type' => EntityType::class,
                'field_options' => [
                    'class' => User::class,
                    'choice_label' => 'fullNameAndEmail',
                    'required' => false,
                ],
            ])
            ->add('status', null, [
                'label' => 'Статус',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => Order::CHOICES,
                    'required' => false,
                    'placeholder' => 'Все статусы',
                ],
            ])
            ->add('customerName', null, ['label' => 'Имя покупателя(на момент покупки)'])
            ->add('deliveryAddress', null, ['label' => 'Адрес доставки'])
            ->add('additionalInfo', null, ['label' => 'Доп описание'])
            ->add('totalAmount', NumberFilter::class, [
                'label' => 'Итоговая сумма(00.00)',
                'field_type' => NumberType::class,
                'field_options' => ['scale' => 2],
            ])
            ->add('createdAt', null, ['label' => 'Дата создания', 'show_filter' => true]);
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
            ->add('orderNumber', null, ['label' => 'Номер заказа'])
            ->add('customerName', null, ['label' => 'Покупатель'])
            ->add('user', null, [
                'label' => 'Пользователь(создал)',
                'field_type' => EntityType::class,
                'field_options' => [
                    'class' => User::class,
                    'choice_label' => 'fullNameAndEmail',
                    'required' => false,
                ],
            ])
            ->add('totalAmount', null, ['label' => 'Сумма'])
            ->add('deliveryAddress', 'boolean', ['label' => 'есть адрес'])
            ->add('additionalInfo', 'boolean', ['label' => 'есть доп инфо'])

            ->add('status', 'choice', ['label' => 'Статус', 'choices' => array_flip(Order::CHOICES)])
            ->add('createdAt', 'datetime', ['label' => 'Создан', 'format' => 'd.m.Y H:i', 'sortable' => true])
            ->add(ListMapper::NAME_ACTIONS, ListMapper::TYPE_ACTIONS, $actions);
    }

    // creating new / editin
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('Основная информация', ['class' => 'col-md-6'])
            ->add('orderNumber', null, ['label' => 'Номер заказа', 'required' => false])
            ->add('customerName', null, ['label' => 'Имя покупателя', 'required' => true])
            ->add('user', EntityType::class, [
                'label' => 'Пользователь',
                'class' => User::class,
                'choice_label' => 'email',
                'required' => true,
                'placeholder' => 'Без пользователя',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Статус',
                'required' => true,
                'choices' => Order::CHOICES,
            ])
            ->end()

            ->with('Метаданные', ['class' => 'col-md-6'])
            ->add('createdAt', DateTimeType::class, self::dTimeFF('Создан'))
            ->add('updatedAt', DateTimeType::class, self::dTimeFF('Изменён'))
            ->add('paidAt', DateTimeType::class, self::dTimeFF('Оплачен'))
            ->add('shippedAt', DateTimeType::class, self::dTimeFF('Отправлен'))
            ->end()

            ->with('Детали', ['class' => 'col-md-12'])
            ->add('totalAmount', MoneyType::class, [
                'label' => 'Общая сумма',
                'currency' => '∑', // &sum; == ∑
                'required' => true,
            ])
            ->add('deliveryAddress', TextareaType::class, [
                'label' => 'Адрес доставки',
                'required' => true,
                'attr' => ['rows' => 3],
            ])
            ->add('additionalInfo', TextareaType::class, [
                'label' => 'Дополнительная информация',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->end()
        ;
    }

    // show is clicked.
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->with('Основная информация', ['class' => 'col-md-6'])
            ->add('id', null, ['label' => 'ID'])
            ->add('orderNumber', null, ['label' => 'Номер заказа'])
            ->add('customerName', null, ['label' => 'Покупатель'])
            ->add('user', null, [
                'label' => 'Пользователь(создал заказ)',
                'class' => User::class,
                'choice_label' => 'fullNameAndEmail',
            ])
            ->add('status', null, ['label' => 'Статус'])
            ->end()
            ->with('Метаданные', ['class' => 'col-md-6'])
            ->add('createdAt', 'datetime', ['label' => 'Создан', 'format' => 'd.m.Y H:i:s'])
            ->add('updatedAt', 'datetime', ['label' => 'Изменён', 'format' => 'd.m.Y H:i:s'])
            ->add('paidAt', 'datetime', ['label' => 'Оплачен', 'format' => 'd.m.Y H:i:s'])
            ->add('shippedAt', 'datetime', ['label' => 'Отправлен', 'format' => 'd.m.Y H:i:s'])
            ->end()
            ->with('Детали заказа', ['class' => 'col-md-12'])
            ->add('totalAmount', null, ['label' => 'Общая сумма'])
            ->add('deliveryAddress', null, ['label' => 'Адрес доставки'])
            ->add('additionalInfo', null, ['label' => 'Дополнительная информация'])
            ->end()
        ;
    }

    /**
     * Предварительная обработка перед сохранением (создание/обновление).
     */
    protected function prePersist(object $object): void
    {
        $this->finalizeOrder($object);
    }

    /**
     * Предварительная обработка перед обновлением
     */
    protected function preUpdate(object $object): void
    {
        $this->finalizeOrder($object);
    }

    /**
     * Финализация заказа перед сохранением
     */
    private function finalizeOrder(Order $order): void
    {
        // Если orderNumber не установлен, генерируем
        if ($order->getOrderNumber() === null || $order->getOrderNumber() === '') {
            $order->setOrderNumber('ORD-'.strtoupper(uniqid()));
        }

        // Если createdAt не установлен, устанавливаем
        if ($order->getCreatedAt() === null) {
            $reflection = new \ReflectionClass($order);
            $prop = $reflection->getProperty('createdAt');
            $prop->setAccessible(true);
            $prop->setValue($order, new \DateTimeImmutable());
        }

        // Инициализируем items через геттер (он сам инициализирует если нужно)
        $order->getItems();
    }
}
