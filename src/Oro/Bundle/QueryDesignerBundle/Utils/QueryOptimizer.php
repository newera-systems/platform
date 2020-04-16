<?php

namespace Oro\Bundle\QueryDesignerBundle\Utils;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\From;
use Doctrine\ORM\Query\Expr\Func;
use Doctrine\ORM\Query\Expr\GroupBy;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class QueryOptimizer
{

    /**
     * @var LoggerInterface\
     */
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        if (!$logger) {
            $logger = new NullLogger();
        }

        $this->logger = $logger;
    }

    public function filterQuery(QueryBuilder $qb): void
    {
        $originalQuery = '';
        if ($this->hasLogger()) {
            $originalQuery = $qb->getQuery()->getSQL();
        }

        $joins = $qb->getDQLPart('join');
        $qb->resetDQLPart('join');

        foreach ($joins as $joinParent) {
            /** @var Join $join */
            foreach ($joinParent as $join) {
                if (self::isJoinUsed($join, $qb)) {
                    self::addJoin($qb, $join);
                }
            }
        }

        if ($this->hasLogger()) {
            $newQuery = $qb->getQuery()->getSQL();

            if ($originalQuery !== $newQuery) {
                $this->logger->notice(
                    sprintf("DQL Optimized : \n   ORIGINAL : %s\n   NEW      : %s", $originalQuery, $newQuery)
                );
            }
        }
    }

    /**
     * @return bool
     */
    private function hasLogger(): bool
    {
        return $this->logger && !$this->logger instanceof NullLogger;
    }

    private static function isJoinUsed(Join $join, QueryBuilder $qb): bool
    {
        if (!self::isLeftJoin($join)) {
            return true;
        }

        if (self::isJoinInPart($qb->getDQLParts(), $join)) {
            return true;
        }

        return false;
    }

    /**
     * @param Join $join
     *
     * @return bool
     */
    private static function isLeftJoin(Join $join): bool
    {
        return strtolower($join->getJoinType()) === 'left';
    }

    private static function isJoinInPart($part, $join): bool
    {
        $alias = $join->getAlias();

        switch (is_object($part) ? get_class($part) : gettype($part)) {
            case 'array':
                foreach ($part as $p) {
                    if (self::isJoinInPart($p, $join)) {
                        return true;
                    }
                }
                break;

            case Select::class:
                /** @var Select $part */
                foreach ($part->getParts() as $p) {
                    if (self::strContains($p, $alias)) {
                        return true;
                    }
                }
                break;
            case From::class  :
                /** @var From $part */

                if (self::strContains($part->getFrom(), $alias)) {
                    return true;
                }
                break;
            case Join::class  :
                /** @var Join $part */
                if (!self::isLeftJoin($join) || self::strContains($part->getCondition(), $alias)) {
                    return true;
                }

                break;
            case Andx::class:
                /** @var Andx $part */

                if (self::isJoinInPart($part->getParts(), $join)) {
                    return true;
                }
                break;
            case Orx::class:
                /** @var Orx $part */

                if (self::isJoinInPart($part->getParts(), $join)) {
                    return true;
                }
                break;
            case Func::class:
                /** @var Func $part */

                foreach ($part->getArguments() as $argument) {
                    if (self::strContains($argument, $alias)) {
                        return true;
                    }
                }
                break;
            case GroupBy::class:
                /** @var GroupBy $part */
                foreach ($part->getParts() as $p) {
                    if (self::strContains($p, $alias)) {
                        return true;
                    }
                }
                break;

            case OrderBy::class:
                /** @var OrderBy $part */
                foreach ($part->getParts() as $p) {
                    if (self::strContains($p, $alias)) {
                        return true;
                    }
                }
                break;
        }

        return false;
    }

    private static function strContains($string, $stringToSearch): bool
    {
        return stripos($string, $stringToSearch) !== false;
    }

    private static function addJoin(QueryBuilder $qb, Join $join): void
    {
        if (self::isLeftJoin($join)) {
            $qb->leftJoin(
                $join->getJoin(),
                $join->getAlias(),
                $join->getConditionType(),
                $join->getCondition(),
                $join->getIndexBy()
            );
        } else {
            $qb->innerJoin(
                $join->getJoin(),
                $join->getAlias(),
                $join->getConditionType(),
                $join->getCondition(),
                $join->getIndexBy()
            );
        }

    }
}
