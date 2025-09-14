<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Http\Compatibility;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use TaiCrm\LaravelModularDdd\Http\Compatibility\Contracts\RequestTransformerInterface;
use TaiCrm\LaravelModularDdd\Http\Compatibility\Contracts\ResponseTransformerInterface;

class VersionTransformer
{
    private array $requestTransformers = [];
    private array $responseTransformers = [];

    public function __construct(
        private TransformationRegistry $registry
    ) {
        $this->loadTransformers();
    }

    public function transformRequest(Request $request, string $fromVersion, string $toVersion): Request
    {
        if (!$this->shouldTransformRequest($fromVersion, $toVersion)) {
            return $request;
        }

        $cacheKey = $this->getRequestCacheKey($request, $fromVersion, $toVersion);

        return Cache::remember($cacheKey, $this->getCacheTtl(), function () use ($request, $fromVersion, $toVersion) {
            $transformer = $this->getRequestTransformer($fromVersion, $toVersion);

            if (!$transformer) {
                return $request;
            }

            return $transformer->transform($request, $fromVersion, $toVersion);
        });
    }

    public function transformResponse(JsonResponse $response, string $fromVersion, string $toVersion): JsonResponse
    {
        if (!$this->shouldTransformResponse($fromVersion, $toVersion)) {
            return $response;
        }

        $cacheKey = $this->getResponseCacheKey($response, $fromVersion, $toVersion);

        $transformedData = Cache::remember($cacheKey, $this->getCacheTtl(), function () use ($response, $fromVersion, $toVersion) {
            $transformer = $this->getResponseTransformer($fromVersion, $toVersion);

            if (!$transformer) {
                return $response->getData(true);
            }

            return $transformer->transform($response->getData(true), $fromVersion, $toVersion);
        });

        $transformedResponse = response()->json($transformedData, $response->getStatusCode());

        // Copy headers from original response
        foreach ($response->headers->all() as $key => $values) {
            $transformedResponse->headers->set($key, $values);
        }

        // Add transformation metadata
        $transformedResponse->headers->set('X-API-Transformed', 'true');
        $transformedResponse->headers->set('X-API-Transform-From', $fromVersion);
        $transformedResponse->headers->set('X-API-Transform-To', $toVersion);

        return $transformedResponse;
    }

    public function registerRequestTransformer(string $fromVersion, string $toVersion, RequestTransformerInterface $transformer): void
    {
        $key = $this->getTransformerKey($fromVersion, $toVersion);
        $this->requestTransformers[$key] = $transformer;
    }

    public function registerResponseTransformer(string $fromVersion, string $toVersion, ResponseTransformerInterface $transformer): void
    {
        $key = $this->getTransformerKey($fromVersion, $toVersion);
        $this->responseTransformers[$key] = $transformer;
    }

    public function hasRequestTransformer(string $fromVersion, string $toVersion): bool
    {
        $key = $this->getTransformerKey($fromVersion, $toVersion);
        return isset($this->requestTransformers[$key]);
    }

    public function hasResponseTransformer(string $fromVersion, string $toVersion): bool
    {
        $key = $this->getTransformerKey($fromVersion, $toVersion);
        return isset($this->responseTransformers[$key]);
    }

    public function getAvailableTransformations(): array
    {
        return [
            'request_transformers' => array_keys($this->requestTransformers),
            'response_transformers' => array_keys($this->responseTransformers),
        ];
    }

    public function canTransform(string $fromVersion, string $toVersion): array
    {
        return [
            'request' => $this->hasRequestTransformer($fromVersion, $toVersion),
            'response' => $this->hasResponseTransformer($fromVersion, $toVersion),
            'bidirectional' => $this->hasRequestTransformer($fromVersion, $toVersion) &&
                              $this->hasResponseTransformer($toVersion, $fromVersion),
        ];
    }

    private function loadTransformers(): void
    {
        // Load transformers from registry
        $transformers = $this->registry->getAllTransformers();

        foreach ($transformers as $transformer) {
            if ($transformer['type'] === 'request') {
                $this->registerRequestTransformer(
                    $transformer['from_version'],
                    $transformer['to_version'],
                    $transformer['instance']
                );
            } elseif ($transformer['type'] === 'response') {
                $this->registerResponseTransformer(
                    $transformer['from_version'],
                    $transformer['to_version'],
                    $transformer['instance']
                );
            }
        }
    }

    private function shouldTransformRequest(string $fromVersion, string $toVersion): bool
    {
        return Config::get('modular-ddd.api.compatibility.request_transformation', true) &&
               $fromVersion !== $toVersion &&
               $this->hasRequestTransformer($fromVersion, $toVersion);
    }

    private function shouldTransformResponse(string $fromVersion, string $toVersion): bool
    {
        return Config::get('modular-ddd.api.compatibility.response_transformation', true) &&
               $fromVersion !== $toVersion &&
               $this->hasResponseTransformer($fromVersion, $toVersion);
    }

    private function getRequestTransformer(string $fromVersion, string $toVersion): ?RequestTransformerInterface
    {
        $key = $this->getTransformerKey($fromVersion, $toVersion);
        return $this->requestTransformers[$key] ?? null;
    }

    private function getResponseTransformer(string $fromVersion, string $toVersion): ?ResponseTransformerInterface
    {
        $key = $this->getTransformerKey($fromVersion, $toVersion);
        return $this->responseTransformers[$key] ?? null;
    }

    private function getTransformerKey(string $fromVersion, string $toVersion): string
    {
        return "{$fromVersion}:{$toVersion}";
    }

    private function getRequestCacheKey(Request $request, string $fromVersion, string $toVersion): string
    {
        $requestHash = md5(serialize([
            'url' => $request->url(),
            'method' => $request->method(),
            'input' => $request->all(),
            'headers' => $request->headers->all(),
        ]));

        return "api_transform:request:{$fromVersion}:{$toVersion}:{$requestHash}";
    }

    private function getResponseCacheKey(JsonResponse $response, string $fromVersion, string $toVersion): string
    {
        $responseHash = md5(serialize([
            'data' => $response->getData(true),
            'status' => $response->getStatusCode(),
        ]));

        return "api_transform:response:{$fromVersion}:{$toVersion}:{$responseHash}";
    }

    private function getCacheTtl(): int
    {
        return Config::get('modular-ddd.api.compatibility.transform_cache_ttl', 3600);
    }
}