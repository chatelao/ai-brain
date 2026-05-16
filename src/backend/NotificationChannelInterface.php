<?php

namespace App;

interface NotificationChannelInterface
{
    /**
     * Sends a notification through the channel.
     *
     * @param array $notification The notification data.
     * @return bool True if sent successfully, false otherwise.
     */
    public function send(array $notification): bool;
}
