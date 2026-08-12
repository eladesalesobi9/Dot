<?php


namespace App\Interfaces;


interface NotifierInterface
{
    public function notify(string $message): array;
    public function isSupported(): bool;
}
