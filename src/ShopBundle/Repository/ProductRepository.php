<?php

namespace ShopBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * ProductRepository
 *
 */
class ProductRepository extends EntityRepository
{

    /**
     * Search in all fields by requested parameters.
     * @param string $orderByColumn
     * @return object
     */
    public function searchRequestedProduct($searchString, $limit, $offset = 0)
    {
        $searchString = "%" . $searchString . "%";

        $query = $this
            ->getEntityManager()
            ->createQuery("SELECT n FROM Product n WHERE n.title LIKE :searchterm")
            ->setParameter('searchterm', $searchString)
        //    ->getQuery()
            ->useQueryCache(true)
            ->useResultCache(true, 3600)
        ;

        $entities = $query->getResult();

        return $entities;
    }

    /**
     * Find all product by filters, if needed simplest see others functions.
     * @param int $categoryId
     * @param string $orderByColumn
     * @param string ASC|DESC $direction
     * @param int $limit
     * @param int $offset
     * @return object
     */
    public function searchProducts($categoryId = false, $orderByColumn, $direction, $limit, $offset = 0)
    {
        $orderByColumn = "u." . $orderByColumn;

        $db = $this->getEntityManager()->createQueryBuilder();
        $db->select('u')
            ->from('ShopBundle:Product', 'u')
            ->where('u.available > 0 ');
            if($categoryId) {
                $db->andWhere('u.category = :categoryId ')
                    ->setParameter('categoryId', $categoryId);
            }
        $db->orderBy($orderByColumn, $direction)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
        ;

        return $db->getQuery()->useQueryCache(true)->useResultCache(true, 3600)->getResult();
    }
    //$products = $this->getDoctrine()->getManager()->$em->getRepository('ShopBundle:Product')->findProducts(...);

    /**
     * Count all products for paginate
     * @return int
     * @throws \Doctrine\ORM\Query\QueryException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAllProducts($categoryId = false)
    {
        $db = $this->createQueryBuilder('p');
        $db->select('COUNT(p)')
            ->where('p.available > 0');
        if($categoryId){
            $db->andWhere('p.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        return $db->getQuery()->useQueryCache(true)->useResultCache(true, 3600)->getSingleScalarResult();
    }

}
