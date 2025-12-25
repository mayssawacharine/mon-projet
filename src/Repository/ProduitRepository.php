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

    /**
     * Méthode pour l'admin - retourne tous les produits avec leur catégorie
     */
    public function findAllWithCategory(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des produits (pour filtres admin)
     */
    public function search(string $search = null, string $category = null, bool $disponible = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c');

        if ($search) {
            $qb->andWhere('p.nom LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($category) {
            $qb->andWhere('c.nom = :category')
                ->setParameter('category', $category);
        }

        if ($disponible !== null) {
            $qb->andWhere('p.disponible = :disponible')
                ->setParameter('disponible', $disponible);
        }

        return $qb->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Méthode existante que tu avais déjà
    public function findFilteredProducts(?string $search, ?string $categoryName, ?string $sortAlpha, ?string $sortPrice): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.category', 'c')
            ->addSelect('c');

        if ($search) {
            $qb->andWhere('p.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($categoryName) {
            $qb->andWhere('c.nom LIKE :catName')
                ->setParameter('catName', '%' . $categoryName . '%');
        }

        if ($sortPrice === 'low') {
            $qb->orderBy('p.prix', 'ASC');
        } elseif ($sortPrice === 'high') {
            $qb->orderBy('p.prix', 'DESC');
        }

        if ($sortAlpha === 'asc') {
            if ($sortPrice) {
                $qb->addOrderBy('p.nom', 'ASC');
            } else {
                $qb->orderBy('p.nom', 'ASC');
            }
        } elseif ($sortAlpha === 'desc') {
            if ($sortPrice) {
                $qb->addOrderBy('p.nom', 'DESC');
            } else {
                $qb->orderBy('p.nom', 'DESC');
            }
        }

        return $qb->getQuery()->getResult();
    }
}