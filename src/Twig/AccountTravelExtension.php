<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\TravelPlanRepository;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Component\Security\Authentication\UserInterface as SuluUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AccountTravelExtension extends AbstractExtension
{
    public function __construct(
        private readonly TravelPlanRepository $travelPlanRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('account_has_active_travel_plan', $this->hasActiveTravelPlan(...)),
        ];
    }

    public function hasActiveTravelPlan(): bool
    {
        $user = $this->security->getUser();
        $contact = $user instanceof SuluUserInterface ? $user->getContact() : null;

        if (!$contact instanceof Contact) {
            return false;
        }

        $today = new \DateTimeImmutable('today');

        foreach ($this->travelPlanRepository->findPublishedByContact($contact) as $travelPlan) {
            $tripProfile = $travelPlan->getContent()['tripProfile'] ?? [];
            $tripProfile = \is_array($tripProfile) ? $tripProfile : [];
            $startDate = $this->createDate($tripProfile['startDate'] ?? null);
            $endDate = $this->createDate($tripProfile['endDate'] ?? null);

            if (
                $startDate instanceof \DateTimeImmutable
                && $endDate instanceof \DateTimeImmutable
                && $startDate <= $today
                && $today <= $endDate
            ) {
                return true;
            }
        }

        return false;
    }

    private function createDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_scalar($value)) {
            return null;
        }

        $value = \trim((string) $value);

        if (1 !== \preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            !$date instanceof \DateTimeImmutable
            || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date;
    }
}
