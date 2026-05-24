<?php

namespace App;

class BrowserChannelHandler implements NotificationChannelInterface
{
    public function send(array $notification, array $actions = []): bool
    {
        // Browser notifications are handled by frontend polling.
        // This handler is a no-op to satisfy the interface.
        return true;
    }

    public function delete(array $notification): bool
    {
        // Browser notifications are handled by frontend polling.
        // This handler is a no-op to satisfy the interface.
        return true;
    }
}
