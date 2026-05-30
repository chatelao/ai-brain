<?php

namespace App;

/**
 * Handler for sending push notifications via Expo's Push API.
 */
class ExpoChannelHandler implements NotificationChannelInterface
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function __construct(private User $userModel)
    {
    }

    public function send(array $notification, array $actions = []): bool
    {
        $userId = $notification['user_id'];
        $user = $this->userModel->findById($userId);

        if (!$user || empty($user['expo_push_token'])) {
            return false;
        }

        $token = $user['expo_push_token'];

        $payload = [
            'to' => $token,
            'title' => $notification['title'],
            'body' => $notification['message'],
            'data' => array_merge($notification['data'] ?? [], [
                'notification_id' => $notification['notification_id']
            ]),
            'sound' => 'default',
        ];

        return $this->sendRequest($payload);
    }

    public function delete(array $notification): bool
    {
        // Push notifications are ephemeral on the device.
        // We don't have a reliable way to "delete" a delivered push notification via Expo API.
        return true;
    }

    private function sendRequest(array $payload): bool
    {
        $ch = curl_init(self::EXPO_PUSH_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-encoding: gzip, deflate',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }
}
