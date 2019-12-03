<?php

namespace App\Repository;

use App\Entity\Remark;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Remark|null find($id, $lockMode = null, $lockVersion = null)
 * @method Remark|null findOneBy(array $criteria, array $orderBy = null)
 * @method Remark[]    findAll()
 * @method Remark[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RemarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remark::class);
    }

    // /**
    //  * @return Remark[] Returns an array of Remark objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Remark
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
