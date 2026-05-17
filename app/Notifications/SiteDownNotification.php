<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteDownNotification extends Notification
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
            ->subject('Alert: '.$this->monitor->url.' is DOWN')
            ->line('Your monitored site is currently DOWN.')
            ->line('URL: '.$this->monitor->url)
            ->line('Detected at: '.now()->toDateTimeString());
    }
}
