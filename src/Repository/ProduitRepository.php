<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    public function findAllWithCategory(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findFilteredProducts(?string $search, ?string $categoryName, ?string $sortPrice, ?float $maxPrice): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.category', 'c')
            ->addSelect('c');

        // 1. Search Filter
        if ($search) {
            $qb->andWhere('p.nom LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // 2. Category Filter
        if ($categoryName) {
            $qb->andWhere('c.nom LIKE :catName')
                ->setParameter('catName', '%' . $categoryName . '%');
        }

        // 3. Max Price Filter (▼▼▼ ADDED THIS BLOCK ▼▼▼)
        if ($maxPrice) {
            $qb->andWhere('p.prix <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        // 4. Sorting Logic
        if ($sortPrice === 'low') {
            $qb->orderBy('p.prix', 'ASC');
        } elseif ($sortPrice === 'high') {
            $qb->orderBy('p.prix', 'DESC');
        } else {
            // Default sort (e.g., by Name) if no specific sort requested
            $qb->orderBy('p.nom', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}