<?php
namespace Firebase\JWT;

interface JWTExceptionWithPayloadInterface
{
    public function getPayload(): object;

    public function setPayload(object $payload): void;
}
