<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteUpNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Monitor $monitor) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Resolved: '.$this->monitor->url.' is back UP')
            ->line('Your monitored site is back UP.')
            ->line('URL: '.$this->monitor->url)
            ->line('Recovered at: '.now()->toDateTimeString());
    }
}
