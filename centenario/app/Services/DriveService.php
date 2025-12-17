<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;

class DriveService
{
    public static function client(): Drive
    {
        $client = new Client();

        // Carga credenciales del Service Account
        $client->setAuthConfig(config('services.google.drive_service'));

        // Solo lectura
        $client->addScope(Drive::DRIVE_READONLY);

        return new Drive($client);
    }
}
