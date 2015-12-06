<?php

namespace TVListings\Tests\Integration\Service;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use TVListings\Domain\Entity\Channel;
use TVListings\Domain\Entity\Listing;
use TVListings\Domain\Service\DoctrineEntityManager;

class DoctrineEntityManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    protected function setUp()
    {
        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__."/../../../src/Domain/Entity"), true);

        $config->setCustomDatetimeFunctions(array(
            'DATE'  => 'DoctrineExtensions\Query\Mysql\Date',
        ));

        // database configuration parameters
        $conn = array(
            'driver' => 'pdo_sqlite',
            'path' => __DIR__ . '/db.sqlite',
        );

        // obtaining the entity manager
        $this->em = EntityManager::create($conn, $config);

        $schemaTool = new SchemaTool($this->em);
        $classes = $this->em->getMetaDataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }

    /**
     * @test
     * @expectedException Doctrine\DBAL\Exception\UniqueConstraintViolationException
     */
    public function it_should_throw_duplicated_exception_when_channel_is_already_exist()
    {
        $em = new DoctrineEntityManager($this->em);

        $channel = new Channel("MNB", "mnb.png");
        $em->persist($channel);

        $channel = new Channel("MNB", "mnb.png");
        $em->persist($channel);
    }

    /**
     * @test
     */
    public function it_should_return_null_if_no_channel_is_found()
    {
        $em = new DoctrineEntityManager($this->em);

        $this->assertEquals(null, $em->findOneBy(Channel::class, "slug", "blah"));
    }

    /**
     * @test
     */
    public function it_should_find_a_channel_by_slug()
    {
        $em = new DoctrineEntityManager($this->em);
        $channel = new Channel("MNB", "mnb.png");
        $em->persist($channel);

        $this->assertEquals("MNB", $em->findOneBy(Channel::class, "slug", "mnb")->getName());
    }

    /**
     * @test
     */
    public function it_should_retrieve_todays_listings_by_channel()
    {
        $em = new DoctrineEntityManager($this->em);
        $channel = new Channel("MNB", "mnb.png");
        $em->persist($channel);

        $channel = $em->findOneBy(Channel::class, "slug", "mnb");
        $yesterdayListing = new Listing($channel, "News", new \DateTime('-1 day'));
        $em->persist($yesterdayListing);
        $todayListing1 = new Listing($channel, "News",  new \DateTime());
        $em->persist($todayListing1);
        $todayListing2 = new Listing($channel, "Friends", new \DateTime());
        $em->persist($todayListing2);

        $criteria = array(
            'channel' => array(
                'builder' => function ($alias) {
                    return sprintf("%s.channel", $alias);
                },
                'value' => $channel
            ),
            'programDate' => array(
               'builder' => function ($alias) {
                    return sprintf("DATE(%s.programDate)", $alias);
               },
               'value' => (new \DateTime())->format('Y-m-d')
            ),
        );

        $listings = $em->findBy(Listing::class, $criteria);

        $this->assertEquals(2, count($listings));
        $this->assertEquals("News", $listings[0]->getTitle());
    }
}
