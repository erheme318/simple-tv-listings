<?php

namespace TVListings\Tests\Repository;

use TVListings\Domain\Entity\Channel;
use TVListings\Domain\Entity\Listing;
use TVListings\Domain\Repository\ListingRepository;

class ListingRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var ListingRepository
     */
    private $repo;

    protected function setUp()
    {
        $this->entityManager = $this->getMock('TVListings\Domain\Service\EntityManager');
        $this->repo = new ListingRepository($this->entityManager);
    }

    /**
     * @test
     */
    public function it_should_persist_channel_to_db()
    {
        $channel = new Channel("MNB", 'mnb.png');
        $listing = new Listing($channel, "News", new \DateTime());

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->repo->persist($listing);
    }

    /**
     * @test
     */
    public function it_should_find_a_listing_by_identity()
    {
        $id = 5;
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(
                $this->equalTo(Listing::class),
                $this->equalTo($id)
            );

        $this->repo->find($id);
    }

    /**
     * @test
     */
    public function it_should_delete_listing()
    {
        $listing = new Listing(new Channel("MNB", 'mnb.png'), "News", new \DateTime());

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($this->equalTo($listing));

        $this->repo->delete($listing);
    }

    /**
     * @test
     */
    public function it_should_retrieve_all_listings_by_channel()
    {
        $channel = new Channel("MNB", 'mnb.png');

        $this->entityManager
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->equalTo(Listing::class)
            )
            ->willReturn(array());

        $this->assertEquals(array(), $this->repo->findBy($channel));
    }
}
