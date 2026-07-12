<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\TravelMemoryAlbum;
use App\Entity\TravelPlan;
use App\Entity\TravelRequest;
use App\Service\TravelRequestRemover;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Media\Exception\MediaNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

final class TravelRequestRemoverTest extends TestCase
{
    public function testRemovesPlanMediaAlbumMediaAndBothEntities(): void
    {
        $travelRequest = new TravelRequest();
        $travelPlan = (new TravelPlan())
            ->setTravelRequest($travelRequest)
            ->setPdfMediaId(41);
        $album = (new TravelMemoryAlbum())
            ->setTravelPlan($travelPlan)
            ->setMediaId(52);

        $entityManager = $this->entityManagerWith($travelRequest, $travelPlan, $album);

        $removed = [];
        $entityManager->method('remove')
            ->willReturnCallback(static function (object $entity) use (&$removed): void {
                $removed[] = $entity;
            });
        $entityManager->expects(self::once())->method('flush');

        $mediaManager = $this->createMock(MediaManagerInterface::class);
        $deletedMedia = [];
        $mediaManager->expects(self::exactly(2))
            ->method('delete')
            ->willReturnCallback(static function (int $id) use (&$deletedMedia): void {
                $deletedMedia[] = $id;
            });

        (new TravelRequestRemover($entityManager, $mediaManager))->remove($travelRequest);

        self::assertSame([41, 52], $deletedMedia);
        self::assertSame([$travelPlan, $travelRequest], $removed);
    }

    public function testRemovesRequestWithoutPlanAndTouchesNoMedia(): void
    {
        $travelRequest = new TravelRequest();

        $entityManager = $this->entityManagerWith($travelRequest, null, null);
        $entityManager->expects(self::once())->method('remove')->with($travelRequest);
        $entityManager->expects(self::once())->method('flush');

        $mediaManager = $this->createMock(MediaManagerInterface::class);
        $mediaManager->expects(self::never())->method('delete');

        (new TravelRequestRemover($entityManager, $mediaManager))->remove($travelRequest);
    }

    public function testMissingMediaDoesNotBlockRemoval(): void
    {
        $travelRequest = new TravelRequest();
        $travelPlan = (new TravelPlan())
            ->setTravelRequest($travelRequest)
            ->setPdfMediaId(41);

        $entityManager = $this->entityManagerWith($travelRequest, $travelPlan, null);
        $entityManager->expects(self::exactly(2))->method('remove');
        $entityManager->expects(self::once())->method('flush');

        $mediaManager = $this->createStub(MediaManagerInterface::class);
        $mediaManager->method('delete')->willThrowException(new MediaNotFoundException('weg'));

        (new TravelRequestRemover($entityManager, $mediaManager))->remove($travelRequest);
    }

    /**
     * @return EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function entityManagerWith(
        TravelRequest $travelRequest,
        ?TravelPlan $travelPlan,
        ?TravelMemoryAlbum $album,
    ): EntityManagerInterface {
        $planRepository = $this->createStub(EntityRepository::class);
        $planRepository->method('findOneBy')->willReturn($travelPlan);

        $albumRepository = $this->createStub(EntityRepository::class);
        $albumRepository->method('findOneBy')->willReturn($album);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [TravelPlan::class, $planRepository],
            [TravelMemoryAlbum::class, $albumRepository],
        ]);

        return $entityManager;
    }
}
