<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Exceptions;

use Exception;

class UnsupportedApiVersionException extends Exception
{
    private string $requestedVersion;
    private array $supportedVersions;

    public function __construct(
        string $message,
        string $requestedVersion,
        array $supportedVersions = [],
        int $code = 406,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->requestedVersion = $requestedVersion;
        $this->supportedVersions = $supportedVersions;
    }

    public function getRequestedVersion(): string
    {
        return $this->requestedVersion;
    }

    public function getSupportedVersions(): array
    {
        return $this->supportedVersions;
    }

    public function toArray(): array
    {
        return [
            'error' => 'Unsupported API Version',
            'message' => $this->getMessage(),
            'requested_version' => $this->requestedVersion,
            'supported_versions' => $this->supportedVersions,
            'code' => $this->getCode(),
        ];
    }
}