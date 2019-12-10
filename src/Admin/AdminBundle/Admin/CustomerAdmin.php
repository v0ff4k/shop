<?php

namespace App\Admin\AdminBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use ShopBundle\Entity\Customer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class CustomerAdmin extends AbstractAdmin
{

    public function preUpdate($object)
    {
        $plainPassword = $object->getPlainPassword();
        if (!empty($plainPassword)) {
            $object->setPlainPassword($plainPassword);
        }
    }

    /**
     * Turns the role's array keys into string <ROLES_NAME> keys.
     * @param array $rolesHierarchy
     * @param bool $niceName
     * @param bool $withChildren
     * @param bool $withGrandChildren
     * @return array
     */
    protected static function flattenRoles($rolesHierarchy, $niceName = false, $withChildren = false, $withGrandChildren = false)
    {
        $flatRoles = [];
        foreach ($rolesHierarchy as $key => $roles) {
            if (!empty($roles)) {
                foreach ($roles as $role) {
                    if (!isset($flatRoles[$role])) {
                        $flatRoles[$role] = $niceName ? self::niceRoleName($role) : $role;
                    }
                }
            }
            $flatRoles[$key] = $niceName ? self::niceRoleName($key) : $key;
            if ($withChildren && !empty($roles)) {
                if (!$recursive) {
                    if ($niceName) {
                        array_walk($roles, function(&$item) {
                            $item = self::niceRoleName($item);
                        });
                    }
                    $flatRoles[$key] .= ' (' . join(', ', $roles) . ')';
                } else {
                    $childRoles = [];
                    foreach ($roles as $role) {
                        $childRoles[$role] = $niceName ? self::niceRoleName($role) : $role;
                        if (!empty($rolesHierarchy[$role])) {
                            if ($niceName) {
                                array_walk($rolesHierarchy[$role], function(&$item) {
                                    $item = self::niceRoleName($item);
                                });
                            }
                            $childRoles[$role] .= ' (' . join(', ', $rolesHierarchy[$role]) . ')';
                        }
                    }
                    $flatRoles[$key] .= ' (' . join(', ', $childRoles) . ')';
                }
            }
        }
        return $flatRoles;
    }

    /**
     * Remove underscors, ROLE_ prefix and uppercase words
     * @param string $role
     * @return string
     */
    protected static function niceRoleName($role)
    {
        return ucwords(strtolower(preg_replace(['/\AROLE_/', '/_/'], ['', ' '], $role)));
    }

    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('id', null, array('label' => 'User id '))
            ->add('username')
            ->add('fullname')
            ->add('address')
            ->add('usernameCanonical')
            ->add('email')
            ->add('emailCanonical')
            ->add('enabled')
            ->add('salt')
            ->add('password')
            ->add('lastLogin')
            ->add('confirmationToken')
            ->add('passwordRequestedAt')
            ->add('roles')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
//            ->addIdentifier('id', null, array('label' => 'User id '))
            ->addIdentifier('username')
            ->addIdentifier('fullname')
            ->addIdentifier('address')
            ->addIdentifier('usernameCanonical')
            ->addIdentifier('email')
            ->addIdentifier('emailCanonical')
            ->addIdentifier('enabled')
            ->addIdentifier('salt', 'boolean')
            ->addIdentifier('password', 'boolean')
            ->addIdentifier('last_login', 'datetime', array(
                'format' => 'd/m/Y H:i',
                'label' => 'Last Login'))
            ->addIdentifier('confirmationToken', 'boolean', array('label' => 'not confirmed?'))
            ->addIdentifier('passwordRequestedAt', 'string', array(
                'template' => 'AdminBundle:CRUD:list_reset_request_status.html.twig'))
            ->addIdentifier('roles', 'choice', array(
                'label' => 'Roles',
                'multiple' => true,
                'delimiter' => ', ',
                'choices' => array(
                    CustomerAdmin::ROLE_SUPER_ADMIN => 'sAdmin',
                    CustomerAdmin::ROLE_ADMIN => 'Admin',
                    CustomerAdmin::ROLE_USER => 'User'
                )
            ))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $container = $this->getConfigurationPool()->getContainer();
        $roles = $container->getParameter('security.role_hierarchy.roles');

        $rolesChoices = self::flattenRoles($roles);


        $formMapper
            ->add('username')
            ->add('fullname')
            ->add('address')
            ->add('email')
            ->add('enabled')
            ->add('enabled', CheckboxType::class, array('label' => 'User is enabled ?'))
            ->add('plainPassword', 'password', array('required' => false, 'label' => 'Password (If unwant to change, leave it BLANK)'))
            ->add('lastLogin')
            ->add('confirmationToken')
            ->add('passwordRequestedAt')
            ->add('roles', 'choice', array(
                'choices' => $rolesChoices,
                'multiple' => true
            ))
        ;
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('username')
            ->add('fullname')
            ->add('address')
            ->add('usernameCanonical')
            ->add('email')
            ->add('emailCanonical')
            ->add('enabled')
            ->add('salt')
            ->add('password')
            ->add('lastLogin')
            ->add('confirmationToken')
            ->add('passwordRequestedAt')
            ->add('roles')
            ->add('id')
        ;
    }
}
