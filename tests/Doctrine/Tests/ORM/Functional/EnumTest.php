<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Tests\Models\Enums\Card;
use Doctrine\Tests\Models\Enums\Suit;
use Doctrine\Tests\Models\Enums\TypedCard;
use Doctrine\Tests\OrmFunctionalTestCase;

use function dirname;
use function sprintf;

/**
 * @requires PHP 8.1
 */
class EnumTest extends OrmFunctionalTestCase
{
    public function setUp(): void
    {
        $this->_em         = $this->getEntityManager(null, new AttributeDriver([dirname(__DIR__, 2) . '/Models/Enums']));
        $this->_schemaTool = new SchemaTool($this->_em);

        parent::setUp();

        if ($this->isSecondLevelCacheEnabled) {
            $this->markTestSkipped();
        }
    }

    /**
     * @param class-string $cardClass
     *
     * @dataProvider provideCardClasses
     */
    public function testEnumMapping(string $cardClass): void
    {
        $this->setUpEntitySchema([$cardClass]);

        $card       = new $cardClass();
        $card->suit = Suit::Clubs;

        $this->_em->persist($card);
        $this->_em->flush();
        $this->_em->clear();

        $fetchedCard = $this->_em->find(Card::class, $card->id);

        $this->assertInstanceOf(Suit::class, $fetchedCard->suit);
        $this->assertEquals(Suit::Clubs, $fetchedCard->suit);
    }

    /**
     * @param class-string $cardClass
     *
     * @dataProvider provideCardClasses
     */
    public function testEnumWithNonMatchingDatabaseValueThrowsException(string $cardClass): void
    {
        $this->setUpEntitySchema([$cardClass]);

        $card       = new $cardClass();
        $card->suit = Suit::Clubs;

        $this->_em->persist($card);
        $this->_em->flush();
        $this->_em->clear();

        $metadata = $this->_em->getClassMetadata($cardClass);
        $this->_em->getConnection()->update(
            $metadata->table['name'],
            [$metadata->fieldMappings['suit']['columnName'] => 'invalid'],
            [$metadata->fieldMappings['id']['columnName'] => $card->id]
        );

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf(
            <<<'EXCEPTION'
Context: Trying to hydrate enum property "%s::$suit"
Problem: Case "invalid" is not listed in enum "Doctrine\Tests\Models\Enums\Suit"
Solution: Either add the case to the enum type or migrate the database column to use another case of the enum
EXCEPTION
            ,
            $cardClass
        ));

        $this->_em->find($cardClass, $card->id);
    }

    /**
     * @return array<string, array{class-string}>
     */
    public function provideCardClasses(): array
    {
        return [
            Card::class => [Card::class],
            TypedCard::class => [TypedCard::class],
        ];
    }
}
