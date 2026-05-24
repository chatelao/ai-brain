<?php

namespace App;

interface NotificationChannelInterface
{
    /**
     * Sends a notification through the channel.
     *
     * @param array $notification The notification data.
     * @param array $actions Optional interactive actions.
     * @return bool True if sent successfully, false otherwise.
     */
    public function send(array $notification, array $actions = []): bool;

    /**
     * Deletes a notification from the channel if possible.
     *
     * @param array $notification The notification data.
     * @return bool True if deleted successfully, false otherwise.
     */
    public function delete(array $notification): bool;
}
