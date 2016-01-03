<?php

namespace TVListings\Tests\Integration\Service;

use Symfony\Component\Yaml\Parser;
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
        $this->em = $this->getEntityManager();

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
    public function it_should_retrieve_listings_by_channel()
    {
        $em = new DoctrineEntityManager($this->em);
        $mnb = new Channel("MNB", "mnb.png");
        $mn25 = new Channel("MN25", "mn25.png");
        $em->persist($mnb);
        $em->persist($mn25);

        $mnbListing = new Listing($mnb, "News", new \DateTime('-1 day'));
        $em->persist($mnbListing);

        $criteria = array(
            'channel' => array(
                'builder' => function ($alias) {
                    return sprintf("%s.channel", $alias);
                },
                'value' => $mnb
            ),
        );

        $listings = $em->findBy(Listing::class, $criteria);

        $this->assertEquals(1, count($listings));
    }

    /**
     * @test
     */
    public function it_should_retrieve_todays_listings_by_channel()
    {
        $em = new DoctrineEntityManager($this->em);
        $channel = new Channel("MNB", "mnb.png");
        $em->persist($channel);

        $yesterdayListing = new Listing($channel, "News", new \DateTime('-1 day'));
        $em->persist($yesterdayListing);
        $todayListing1 = new Listing($channel, "News",  new \DateTime());
        $todayListing1->programAt('21:00');
        $em->persist($todayListing1);
        $todayListing2 = new Listing($channel, "Friends", new \DateTime());
        $todayListing2->programAt('12:00');
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
            'orderBy' => array(
               'builder' => function ($alias) {
                    return sprintf("%s.programmedTime", $alias);
               },
               'value' => 'ASC',
            ),
        );

        $listings = $em->findBy(Listing::class, $criteria);

        $this->assertEquals(2, count($listings));
        $this->assertEquals('12:00', $listings[0]->getProgrammedTime());
        $this->assertEquals("Friends", $listings[0]->getTitle());
    }

    /**
     * @test
     */
    public function it_should_remove_entity()
    {
        $em = new DoctrineEntityManager($this->em);
        $channel = new Channel("MNB", "mnb.png");
        $em->persist($channel);

        $this->assertEquals(1, count($em->findAll(Channel::class)));

        $em->remove($channel);
        $this->assertEquals(0, count($em->findAll(Channel::class)));
    }

    /**
     * @test
     */
    public function it_should_find_a_listing_by_identity()
    {
        $em = new DoctrineEntityManager($this->em);
        $channel = new Channel("MNB", 'mnb.png');
        $em->persist($channel);

        $listing = new Listing($channel, "News", new \DateTime());
        $em->persist($listing);

        $this->assertEquals($listing, $em->find(Listing::class, $listing->getId()));
    }

    /**
     * @test
     */
    public function it_should_retrieve_channel_listings_on_specified_date()
    {
        $em = new DoctrineEntityManager($this->em);
        $channel = new Channel("MNB", "mnb.png");
        $em->persist($channel);

        $yesterdayListing = new Listing($channel, "Yesterday's News", new \DateTime('-1 day'));
        $em->persist($yesterdayListing);

        $todayListing = new Listing($channel, "Today's News",  new \DateTime());
        $todayListing->programAt('21:00');
        $em->persist($todayListing);

        $tomorrowListing = new Listing($channel, "Tommorrow's News", new \DateTime('+1 day'));
        $tomorrowListing->programAt('12:00');
        $em->persist($tomorrowListing);

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
               'value' => (new \DateTime('+1 day'))->format('Y-m-d')
            ),
            'orderBy' => array(
               'builder' => function ($alias) {
                    return sprintf("%s.programmedTime", $alias);
               },
               'value' => 'ASC',
            ),
        );

        $listings = $em->findBy(Listing::class, $criteria);

        $this->assertEquals(1, count($listings));
        $this->assertEquals('12:00', $listings[0]->getProgrammedTime());
        $this->assertEquals("Tommorrow's News", $listings[0]->getTitle());
    }

    /**
     * @test
     */
    public function it_should_order_by_programmed_time()
    {
        $em = new DoctrineEntityManager($this->em);
        $channel = new Channel("MNB", "mnb.png");
        $em->persist($channel);

        $todayListing = new Listing($channel, "Morning News",  new \DateTime());
        $todayListing->programAt('7:00');
        $em->persist($todayListing);

        $todayListing = new Listing($channel, "Afternoon News",  new \DateTime());
        $todayListing->programAt('12:00');
        $em->persist($todayListing);

        $todayListing = new Listing($channel, "Evening News",  new \DateTime());
        $todayListing->programAt('21:00');
        $em->persist($todayListing);

        $criteria = array(
            'orderBy' => array(
               'builder' => function ($alias) {
                    return sprintf("%s.programDate", $alias);
               },
               'value' => 'ASC',
            ),
        );

        $listings = $em->findBy(Listing::class, $criteria);

        $this->assertEquals('7:00', $listings[0]->getProgrammedTime());
        $this->assertEquals("Morning News", $listings[0]->getTitle());

        $this->assertEquals('12:00', $listings[1]->getProgrammedTime());
        $this->assertEquals("Afternoon News", $listings[1]->getTitle());
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        if ($this->em) {
            return $this->em;
        }

        $yaml = new Parser();
        try {
            $configuration = $yaml->parse(file_get_contents(__DIR__ . '/../../../app/config/config.yml'));
        } catch (ParseException $e) {
            printf("Unable to parse the YAML string: %s", $e->getMessage());
        }

        $doctrineConfig = $configuration['settings']['doctrine'];

        // database configuration parameters
        $conn = array(
            'driver' => $doctrineConfig['driver'],
            'host' => $doctrineConfig['host'],
            'dbname' => $doctrineConfig['dbname'],
            'user' => $doctrineConfig['user'],
            'password' => $doctrineConfig['password'],
        );

        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__."/../../../src/Domain/Entity"), true);

        $config->setCustomDatetimeFunctions(array(
            'DATE'  => 'DoctrineExtensions\Query\Mysql\Date',
        ));

        // obtaining the entity manager
        return EntityManager::create($conn, $config);
    }
}
