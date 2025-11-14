<?php
// app/Notifications/OrderStatusUpdated.php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public $order;
    public $oldStatus;
    public $newStatus;

    public function __construct(Order $order, $oldStatus, $newStatus)
    {
        $this->order = $order;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $subject = $this->getEmailSubject();
        $greeting = $this->getEmailGreeting();
        $message = $this->getEmailMessage();

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($message)
            ->line("Numéro de commande: {$this->order->order_number}")
            ->line("Nouveau statut: {$this->order->status_label}");

        // Ajouter le numéro de suivi si disponible
        if ($this->order->tracking_number && $this->newStatus === 'shipped') {
            $mail->line("Numéro de suivi: {$this->order->tracking_number}");
        }

        $mail->action('Voir ma commande', url("/my-orders/{$this->order->id}"))
            ->line('Merci de votre confiance!');

        return $mail;
    }

    public function toArray($notifiable)
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'new_status_label' => $this->order->status_label,
            'tracking_number' => $this->order->tracking_number,
            'message' => $this->getNotificationMessage(),
            'type' => 'order_status_updated'
        ];
    }

    private function getEmailSubject()
    {
        switch ($this->newStatus) {
            case 'confirmed':
                return 'Commande confirmée - ' . $this->order->order_number;
            case 'processing':
                return 'Commande en cours de préparation - ' . $this->order->order_number;
            case 'shipped':
                return 'Commande expédiée - ' . $this->order->order_number;
            case 'delivered':
                return 'Commande livrée - ' . $this->order->order_number;
            case 'cancelled':
                return 'Commande annulée - ' . $this->order->order_number;
            default:
                return 'Mise à jour de votre commande - ' . $this->order->order_number;
        }
    }

    private function getEmailGreeting()
    {
        return "Bonjour {$this->order->user->name},";
    }

    private function getEmailMessage()
    {
        switch ($this->newStatus) {
            case 'confirmed':
                return 'Votre commande a été confirmée et est en cours de traitement.';
            case 'processing':
                return 'Votre commande est actuellement en cours de préparation.';
            case 'shipped':
                return 'Bonne nouvelle ! Votre commande a été expédiée.';
            case 'delivered':
                return 'Votre commande a été livrée avec succès. Nous espérons que vous êtes satisfait(e) de votre achat.';
            case 'cancelled':
                return 'Votre commande a été annulée. Si vous avez des questions, n\'hésitez pas à nous contacter.';
            default:
                return "Le statut de votre commande a été mis à jour vers: {$this->order->status_label}";
        }
    }

    private function getNotificationMessage()
    {
        return $this->getEmailMessage();
    }
}