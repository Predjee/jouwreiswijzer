<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationController extends AbstractController
{
    use AccountCustomerTrait;

    #[Route('/account/notifications', name: 'account_notifications', methods: ['GET'])]
    public function notifications(NotificationRepository $notificationRepository): Response
    {
        [, $contact] = $this->getCustomer();

        return $this->render('account/notifications.html.twig', [
            'contact' => $contact,
            'notifications' => $notificationRepository->findForContact($contact),
            'unread_notification_count' => $notificationRepository->countUnreadForContact($contact),
        ]);
    }

    #[Route('/account/notifications/{id}/read', name: 'account_notification_read', methods: ['POST'])]
    public function markNotificationAsRead(
        int $id,
        Request $request,
        NotificationRepository $notificationRepository,
        NotificationService $notificationService,
    ): Response {
        [, $contact] = $this->getCustomer();
        $notification = $notificationRepository->find($id);

        if (
            null === $notification
            || $notification->getRecipientContact()?->getId() !== $contact->getId()
        ) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'account_notification_read_' . $notification->getId(),
            $request->request->getString('_csrf_token'),
        )) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        $notificationService->markAsRead($notification);

        return $this->redirect($notification->getUrl() ?: $this->generateUrl('account'));
    }

    #[Route('/account/notifications/read-all', name: 'account_notifications_read_all', methods: ['POST'])]
    public function markAllNotificationsAsRead(
        Request $request,
        NotificationService $notificationService,
    ): Response {
        [, $contact] = $this->getCustomer();

        if (!$this->isCsrfTokenValid(
            'account_notifications_read_all_' . $contact->getId(),
            $request->request->getString('_csrf_token'),
        )) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        $notificationService->markAllAsRead($contact);

        return $this->redirectToRoute('account_notifications');
    }
}
